<template>
	<pkp-side-modal-body data-cy="create-translation-modal">
		<template #title>
			{{ t('plugins.generic.doiForTranslation.createTranslation') }}
		</template>
		<pkp-side-modal-layout-basic>
			<pkp-form
				v-bind="form"
				@set="set"
				@success="handleSuccess"
				@cancel="closeModal"
			/>
		</pkp-side-modal-layout-basic>
	</pkp-side-modal-body>
</template>

<script setup>
import {inject} from 'vue';

const {useForm} = pkp.modules.useForm;
const {useLocalize} = pkp.modules.useLocalize;
const {t} = useLocalize();

const props = defineProps({
	formConfig: {type: Object, required: true},
});

const {form, set} = useForm(props.formConfig);
const closeModal = inject('closeModal');

function handleSuccess() {
	closeModal();
	window.location.reload();
}
</script>
