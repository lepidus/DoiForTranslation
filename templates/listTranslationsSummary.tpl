<div class="translations">
    {translate key="plugins.generic.submissionsTranslation.translations.article"}:&nbsp;
    {foreach from=$translations item=$translation}
        <a href="{$translation['url']}">{$translation['localeName']}</a>&nbsp;
    {/foreach}
</div>