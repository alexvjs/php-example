<? /// Описание элементов: https://yandex.ru/support/zen/website/rss-modify.html
/** @var CYandexZenComponent $this */

//header("Content-Type: application/rss+xml; charset=".LANG_CHARSET);
//header('Content-Type: application/rss+xml; charset=utf-8');
header('Content-Type: text/xml; charset=utf-8');
//header("Pragma: no-cache");
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:media="http://search.yahoo.com/mrss/"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:georss="http://www.georss.org/georss">
    <channel>
        <title><?=$arParams['CHANNEL_TITLE'] ?: $arResult['SITE_NAME']; // Название сайта. ?></title>
        <link><?=$arParams['CHANNEL_URL'] ?: $arResult['SERVER_NAME']; // URL сайта. ?></link>
        <description><?=$arParams['CHANNEL_DESCRIPTION']; // Описание сайта. ?></description>
        <language>ru</language><?//https://ru.wikipedia.org/wiki/ISO_639-1?>
        <?foreach ($arResult['ITEMS'] as $arItem) {?>
            <item>
                <title><?=$arItem['TITLE'];?></title>
                <link><?=$arItem['LINK'];?></link>
                <pubDate><?=$arItem['PUBDATE'];?></pubDate>
                <author><?=$arItem['AUTHOR_NAME'] ?: $arParams['DEFAULT_AUTHOR_NAME'] ?: $arResult['SITE_NAME']; // (Обяз.) Имя автора ?></author>
                <media:rating scheme="urn:simple">nonadult</media:rating>
                <guid><?=$arItem['GUID'] // Уникальный идентификатор статьи для избежания дублей при повторной отправке.?></guid>
                <?
                /// (Необязательно) Тематика.
                $categories = []; // Дизайн, Дом?
                foreach ($categories as $category) {?>
                    <category><?=$category;?></category>
                <?}
                /// (Обязательно) enclosure. Описание изображений, аудио- и видеофайлов в материале
                foreach ($arItem['ENCLOSURES'] as $enclosure) { ?>
                    <enclosure
                            url="<?=$enclosure['url']?>"
                            type="<?=$enclosure['type']?>"
                    />
                <?}
                /// (Необязательно) Краткое содержание.
                if (!empty($arItem['DESCRIPTION'])) { ?>
                    <description><![CDATA[<?=$arItem['DESCRIPTION'];?>]]></description>
                <?}
                /// (Обязательно) Полный текст. ?>
                <content:encoded><![CDATA[<?=$arItem['CONTENT'];?>]]></content:encoded>
            </item>
        <?}?>
    </channel>
</rss>
<?die();