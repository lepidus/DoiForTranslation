<template>
	<div class="flex gap-x-4" data-cy="doi-for-translation-actions">
		<pkp-spinner v-if="isLoading" :message="t('common.loading')" />

		<pkp-button
			v-else-if="translationData?.translatedSubmission"
			element="a"
			:href="translationData.translatedSubmission.url"
		>
			{{ t('plugins.generic.doiForTranslation.translatedSubmission') }}
		</pkp-button>

		<template v-else-if="translationData">
			<pkp-button
				v-if="translationData.createTranslationForm"
				data-cy="create-translation"
				@click="openCreateTranslationModal"
			>
				{{ t('plugins.generic.doiForTranslation.createTranslation') }}
			</pkp-button>

			<pkp-dropdown
				v-if="translationData.translations.length"
				data-cy="translation-list"
				:label="t('plugins.generic.doiForTranslation.translations')"
				has-dropdown-icon
			>
				<ul>
					<li
						v-for="translation in translationData.translations"
						:key="translation.locale"
					>
						<a class="pkpDropdown__action" :href="translation.url">
							{{ translation.localeName }}
						</a>
					</li>
				</ul>
			</pkp-dropdown>
		</template>
	</div>
</template>

<script setup>
import {computed, watch} from 'vue';
import CreateTranslationModal from './CreateTranslationModal.vue';

const {useFetch} = pkp.modules.useFetch;
const {useLocalize} = pkp.modules.useLocalize;
const {useModal} = pkp.modules.useModal;
const {useUrl} = pkp.modules.useUrl;

const {t} = useLocalize();
const {openSideModal} = useModal();

const props = defineProps({
	submission: {type: Object, required: true},
});

const submissionId = computed(() => props.submission?.id);
const contextId = computed(() => props.submission?.contextId);
const endpoint = computed(
	() => `contexts/${contextId.value}/doiForTranslation`,
);
const query = computed(() => ({submissionId: submissionId.value}));
const {apiUrl} = useUrl(endpoint, query);
const {
	data: translationData,
	fetch: fetchTranslationData,
	isLoading,
} = useFetch(apiUrl);

watch(
	submissionId,
	(id) => {
		if (id) {
			fetchTranslationData({clearData: true});
		}
	},
	{immediate: true},
);

function openCreateTranslationModal() {
	openSideModal(CreateTranslationModal, {
		formConfig: translationData.value.createTranslationForm,
	});
}
</script>
