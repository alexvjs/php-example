<?

class CYandexZenComponent extends CBitrixComponent
{
    private function getSite()
    {
        $arSite = CSite::GetByID(SITE_ID)->Fetch();
        $this->arResult['SITE_NAME'] = $arSite['SITE_NAME']; // Название сайта, Ангстрем
        $this->arResult['SERVER_NAME'] = $arSite['SERVER_NAME']; // URL сайта, www.angstrem-mebel.ru
    }

//    private function getAbsPath($imgURL)
//    {
//        return $_SERVER['DOCUMENT_ROOT'] . $imgURL;
//    }
    
    private function getAbsURL($imgURL)
    {
        if (substr($imgURL, 0, 2) == '//')
            return $imgURL;
        return CHTTP::URN2URI($imgURL);
    }
    
    /** Парсит урлы картинок и изменяет html
     *  Заполняет CONTENT, IMAGE_URLS
     */
    private function processArticleHTML($html)
    {
        // Указать кодировку вначале, либо utf8_decode($doc->saveHTML)
        // <html><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><body>
        $result = [];
        /* @var DOMDocument $dom */
        $dom = DOMDocument::loadHTML(
            '<?xml version="1.0" encoding="UTF-8"?>' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);
        $imgs = $xpath->query('//img');
        $result['IMAGE_URLS'] = [];
        foreach ($imgs as $img)
        {
            /* @var DOMElement $img */
            $src = $img->getAttribute('src');
            $src = $this->getAbsURL($src);
            $result['IMAGE_URLS'][] = $src;
            $img->setAttribute('src', $src);
            $figure = $img->ownerDocument->createElement('figure');
            $img->parentNode->replaceChild($figure, $img);
            $figure->appendChild($img);
            /* // Для подписей https://yandex.ru/support/zen/website/rss-modify.html#ariaid-title5
            $figcaption = $img->ownerDocument->createElement('figcaption', 'Надпись');
            $figure->appendChild($figcaption);
            $span = $img->ownerDocument->createElement('span', 'copyright Автор');
            $span->setAttribute('class', 'copyright');
            $figcaption->appendChild($span);
            */
        }
        $html = $dom->saveHTML($dom->documentElement); // Если () то русские буквы будут в виде &#1085;
        $html = strip_tags($html, $this->allowable_tags);
        $result['CONTENT'] = $html;
        return $result;
    }
    
    private function processArticle($arItem)
    {
        if (empty($arItem['PREVIEW_TEXT']))
            $arItem['PREVIEW_TEXT'] = $arItem['DETAIL_TEXT'];
        if (empty($arItem['PREVIEW_PICTURE']))
            $arItem['PREVIEW_PICTURE'] = $arItem['DETAIL_PICTURE'];
        
        /// Изображения:
        $result = $this->processArticleHTML($arItem['DETAIL_TEXT']);
        $result['ENCLOSURES'] = [];
        $pic = CFile::GetFileArray($arItem['PREVIEW_PICTURE']);
        $result['ENCLOSURES'][] = ['url' => $this->getAbsURL($pic['SRC']), 'type' => $pic['CONTENT_TYPE']];
        foreach ($result['IMAGE_URLS'] as $imgURL) {
            $result['ENCLOSURES'][] = [
                'url' => $imgURL,
                // 'type' => image_type_to_mime_type(exif_imagetype($this->getAbsPath($imgURL))),
                // Если выдаст octet-stream, значит была проблема с доступом к этому url (напр. проблема с сертификатом на тесте):
                'type' => image_type_to_mime_type(exif_imagetype($this->getAbsURL($imgURL))),
            ];
        }
        
        /// Остальное:
        $result['TITLE'] = $arItem['NAME'];
        $result['PUBDATE'] = (new \DateTime($arItem['DATE_ACTIVE_FROM'] ?: $arItem['DATE_CREATE']))->format('D, d M Y H:i:s O');
        $result['LINK'] = $this->getAbsURL($arItem['DETAIL_PAGE_URL']);
        $result['GUID'] = $arItem['XML_ID'] ?: $arItem['ID'];
        $desc = $arItem['PREVIEW_TEXT'];
        $desc = strip_tags($desc, $this->allowable_tags);
        $obParser = new CTextParser;
        $result['DESCRIPTION'] = $obParser->html_cut($desc, $this->arParams['DESC_MAXLEN'] ?: 300);
        // author
        $author = CIBlockElement::GetByID($arItem['PROPERTIES']['AUTHOR']['VALUE'])->Fetch();
        $result['AUTHOR_NAME'] = $author ? $author['NAME'] : '';
        return $result;
    }
    
    private function getArticles()
    {
        Bitrix\Main\Loader::includeModule("iblock");
        $arParams = $this->arParams;
        $arSort = [];
        if (!empty($arParams['SORT_FIELD'])) {
            if (!empty($arParams['SORT_ORDER'])) {
                $arSort[$arParams['SORT_FIELD']] = $arParams['SORT_ORDER'];
            } else {
                $arSort[$arParams['SORT_FIELD']] = 'desc';
            }
        } else {
            $arSort['active_from'] = 'desc';
        }
        
        $arFilter = [
            "IBLOCK_LID" => SITE_ID,
            "IBLOCK_ACTIVE" => "Y",
            "ACTIVE" => "Y",
            "CHECK_PERMISSIONS" => "Y",
            'CHECK_DATES' => 'Y',
        ];
        
        if (count($arParams["IBLOCK_ID"]) > 0)
            $arFilter["IBLOCK_ID"] = $arParams["IBLOCK_ID"];
        
        if (count($arParams["ELEMENT_ID"]) > 0)
            $arFilter["ID"] = $arParams["ELEMENT_ID"];
        
        if (!empty($arParams['FILTER_NAME']) && isset($GLOBALS[$arParams['FILTER_NAME']])) {
            $arFilter = array_merge(
                $arFilter,
                $GLOBALS[$arParams['FILTER_NAME']]
            );
        }
        
        $rsElement = CIBlockElement::GetList($arSort, $arFilter, false, ['nTopCount' => $arParams["COUNT"]]);
        $this->arResult['ITEMS'] = [];
        while ($obElement = $rsElement->GetNextElement()) {
            $arItem = $obElement->GetFields();
            $arItem['PROPERTIES'] = $obElement->GetProperties();
            $this->arResult['ITEMS'][] = $this->processArticle($arItem);
        }
    }
    
    public function executeComponent()
    {
        if ($this->startResultCache()) // TODO: подходящее кэширование.
        {
            $GLOBALS['APPLICATION']->RestartBuffer();
            $this->allowable_tags = '<figure><figcaption><img><span><a><blockquotes><h1><h2><h3><h4><h5><h6><br>'; // p iframe
            $this->getSite();
            $this->getArticles();
            $this->IncludeComponentTemplate();
        }
    }
}