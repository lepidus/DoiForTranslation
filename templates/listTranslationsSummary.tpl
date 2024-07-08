<div id="translations_article-{$article->getId()}" class="summary_translations">
    {translate key="plugins.generic.DoiForTranslation.translations.article"}:&nbsp;
    {foreach from=$translations item=$translation}
        <a href="{$translation['url']}">{$translation['localeName']}</a>&nbsp;
    {/foreach}
</div>

<script>
    function insertAfter(newNode, referenceNode) {ldelim}
        referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
    {rdelim}
    
    function updateTranslationsPosition(){ldelim}
        const translationsDiv = document.getElementById('translations_article-{$article->getId()}');
        const articleSummary = translationsDiv.parentNode;
        const authorsNode = articleSummary.getElementsByClassName('authors')[0];

        insertAfter(translationsDiv, authorsNode);
    {rdelim}
    
    updateTranslationsPosition();
</script>