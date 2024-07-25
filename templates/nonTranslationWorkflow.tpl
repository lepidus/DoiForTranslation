<pkp-button
    ref="createTranslationButton"
    @click="$modal.show('createTranslation')"
>
    {translate key="plugins.generic.doiForTranslation.createTranslation"}
</pkp-button>
<modal
    v-bind="MODAL_PROPS"
    name="createTranslation"
    @closed="setFocusToRef('createTranslationButton')"
>
    <modal-content
        id="createTranslationModal"
        modal-name="createTranslation"
        title="{translate key="plugins.generic.doiForTranslation.createTranslation"}"
    >
        <pkp-form v-bind="components.createTranslationForm" @set="set" @success="location.reload()"></pkp-form>
    </modal-content>
</modal>
{if $hasTranslations}
    <span class="pkpPublication__translation">
        <dropdown
            class="pkpPublication__translations"
            label="{translate key="plugins.generic.doiForTranslation.translations"}"
        >
            <ul>
                {foreach from=$translations item=$translation}
                    <li>
                        <a class="pkpDropdown__action" href="{$translation['url']}">
                            {$translation['localeName']}
                        </a>
                    </li>
                {/foreach}
            </ul>
        </dropdown>
    </span>
{/if}