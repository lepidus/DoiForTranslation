Cypress.Commands.add('findSubmission', function(tab, title) {
	const viewName = tab === 'archive' ? 'Published' : 'Active submissions';
	cy.get('nav').contains(viewName).click();
	cy.contains('table tr', title)
		.contains('button', /^\s*View\s*$/)
		.scrollIntoView()
		.should('be.visible')
		.click({force: true});
});

Cypress.Commands.add('openTranslation', function(localeName) {
	cy.get('[data-cy="translation-list"] button').click();
	cy.get('[data-cy="translation-list"] a:contains("' + localeName + '")').click();
});

Cypress.Commands.add('createTranslation', function(locale) {
	cy.get('[data-cy="create-translation"]').click();
	cy.get('[data-cy="create-translation-modal"] select[name="translationLocale"]').select(locale);
	cy.get('[data-cy="create-translation-modal"] button:contains("Create")').click();
	cy.get('[data-cy="create-translation-modal"]').should('not.exist');
});

Cypress.Commands.add('updateTranslationTitleAbstract', function(locale, title, abstract) {
	cy.openWorkflowMenu('Title & Abstract');
	cy.setTinyMceContent('titleAbstract-title-control-' + locale, title);
	cy.setTinyMceContent('titleAbstract-abstract-control-' + locale, abstract);
	cy.get('#titleAbstract-title-control-' + locale).click({force: true});

	cy.get('button.pkpFormLocales__locale').contains('English').click();
	cy.setTinyMceContent('titleAbstract-title-control-en', '');
	cy.setTinyMceContent('titleAbstract-abstract-control-en', '');
	cy.get('#titleAbstract-title-control-en').click({force: true});

	cy.get('[data-cy="active-modal"] button').contains('Save').click();
	cy.get('[data-cy="active-modal"] [role="status"]').contains('Saved');
});

Cypress.Commands.add('openSubmissionInLocale', function(title, locale) {
	cy.request('index.php/publicknowledge/api/v1/submissions?searchPhrase=' + encodeURIComponent(title)).then(response => {
		const submission = response.body.items.find(item => item.locale === locale);
		cy.visit('index.php/publicknowledge/workflow/access/' + submission.id);
	});
});
