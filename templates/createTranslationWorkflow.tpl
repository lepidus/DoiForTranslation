<pkp-button
    ref="createTranslationButton"
    @click="$modal.show('createTranslation')"
>
    {translate key="plugins.generic.submissionsTranslation.createTranslation"}
</pkp-button>
<modal
    v-bind="MODAL_PROPS"
    name="createTranslation"
    @closed="setFocusToRef('createTranslationButton')"
>
    <modal-content
        id="createTranslationModal"
        modal-name="createTranslation"
        title="{translate key="plugins.generic.submissionsTranslation.createTranslation"}"
    >
        <pkp-form v-bind="components.createTranslationForm" @set="set" @success="location.reload()"></pkp-form>
    </modal-content>
</modal>