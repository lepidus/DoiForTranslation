<div id="translationOf_article-{$article->getId()}" class="summary_translationOf">
    {translate key="plugins.generic.DoiForTranslation.translationOf" locale=$translationLocale}&nbsp;
    <a href="{$translatedSubmission['url']}">{$translatedSubmission['title']|escape}</a>
</div>

<script>
    function insertAfter(newNode, referenceNode) {ldelim}
        referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
    {rdelim}
    
    function updateTranslationsPosition(){ldelim}
        const translationOfDiv = document.getElementById('translationOf_article-{$article->getId()}');
        const articleSummary = translationOfDiv.parentNode;
        const authorsNode = articleSummary.getElementsByClassName('authors')[0];

        insertAfter(translationOfDiv, authorsNode);
    {rdelim}
    
    updateTranslationsPosition();
</script>