describe('DOI For Translation - Plugin setup', function () {
    it('Enables DOI For Translation plugin', function () {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.get('a:contains("Website")').click();

		cy.waitJQuery();
		cy.get('button#plugins-button').click();

		cy.get('input[id^=select-cell-doifortranslationplugin]').check();
		cy.get('input[id^=select-cell-doifortranslationplugin]').should('be.checked');
    });
	it('Enables brazilian portuguese language', function() {
		cy.login('admin', 'admin', 'publicknowledge');

		cy.contains('a', 'Administration').click();
		cy.contains('a', 'Site Setting').click();
		cy.contains('a', 'Install Locale').click();

		cy.get('input[value="pt_BR"]').check();
		cy.get('#installLanguageForm button:contains("Save")').click();
		cy.waitJQuery();

		cy.get('.app__contexts button').click();
		cy.contains('a', 'Journal of Public Knowledge').click();
		cy.contains('a', 'Website').click();
		
		cy.get('#setup-button').click();
		cy.get('#languages-button').click();
		cy.get('input[id^="select-cell-pt_BR-uiLocale"]').check();
		cy.waitJQuery();
		cy.get('input[id^="select-cell-pt_BR-formLocale"]').check();
		cy.waitJQuery();
		cy.get('input[id^="select-cell-pt_BR-submissionLocale"]').check();
		cy.waitJQuery();
	});
});