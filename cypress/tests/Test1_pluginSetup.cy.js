describe('DOI For Translation - Plugin setup', function () {
    it('Enables DOI For Translation plugin', function () {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.visit('index.php/publicknowledge/management/settings/website');
		cy.get('button[id="plugins-button"]').click();

		cy.get('input[id^=select-cell-doifortranslationplugin]').check();
		cy.contains('The plugin "DOI For Translation" has been enabled.');
    });
	it('Enables brazilian portuguese language', function() {
		cy.login('admin', 'admin', 'publicknowledge');

		cy.visit('index.php/index/admin/settings');
		cy.get('button[id="languages-button"]').click();
		cy.get('a[id^=component-grid-admin-languages-adminlanguagegrid-installLocale-button]').click();
		cy.get('input[name="localesToInstall[pt_BR]"]').check();
		cy.get('#installLanguageForm button:contains("Save")').click();
		cy.waitJQuery();
		cy.get('#languageGridContainer').contains('td', 'pt_BR');

		cy.visit('index.php/publicknowledge/management/settings/website');
		cy.get('button[id="setup-button"]').click();
		cy.get('button[id="languages-button"]').click();
		cy.get('input[id^="select-cell-pt_BR-uiLocale"]').check();
		cy.waitJQuery();
		cy.get('input[id^="select-cell-pt_BR-formLocale"]').check();
		cy.waitJQuery();
		cy.get('a[id^=component-grid-settings-languages-submissionlanguagegrid-addLanguageModal-button]').click();
		cy.get('input[name="localesToAdd[pt_BR]"]').check();
		cy.get('#addLanguageForm button[name="submitFormButton"]').click();
		cy.contains('Submission locales updated.');
		cy.get('input[id^="select-cell-pt_BR-submissionLocale"]').check();
		cy.get('input[id^="select-cell-pt_BR-submissionLocale"]').should('be.checked');
	});
});
