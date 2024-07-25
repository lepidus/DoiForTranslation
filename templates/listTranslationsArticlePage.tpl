<section class="item translations">
    <h2 class="label">{translate key="plugins.generic.doiForTranslation.translations.article"}</h2>
    <ul class="translations">
    {foreach from=$translations item=$translation}
        <li>
            <a href="{$translation['url']}">
                {$translation['title']|escape}
            </a>
            &nbsp;({$translation['localeName']})
        </li>
    {/foreach}
    </ul>
</section>