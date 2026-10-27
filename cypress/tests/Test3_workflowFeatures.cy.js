import '../support/commands.js';

describe('DOI For Translation - Workflow features', function () {
    let submissionData;

    before(function() {
        submissionData = {
            'title': {
                'en': 'The principles of XP',
                'fr_CA': 'Les principes de XP',
				'pt_BR': 'Os princípios da XP'
            },
			'abstract': {
                'en': 'Just a simple abstract',
                'fr_CA': 'Juste un simple résumé',
				'pt_BR': 'Apenas um simples resumo'
            },
		}
    });
    it('List of translations of a submission', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.openSubmissionInLocale(submissionData.title['en'], 'en');

        cy.get('[data-cy="translation-list"] button').click();
        cy.get('[data-cy="translation-list"] li a').should('have.length', 1);
        cy.get('[data-cy="translation-list"] a:contains("French (Canada)")').click();

        cy.get('[data-cy="active-modal"]').contains(submissionData.title['fr_CA']);
    });
    it('Locales are hidden from create translation form', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.openSubmissionInLocale(submissionData.title['en'], 'en');

        cy.get('[data-cy="create-translation"]').click();
        cy.get('select[name="translationLocale"] option[value="en"]').should('not.exist');
        cy.get('select[name="translationLocale"] option[value="fr_CA"]').should('not.exist');
        cy.get('select[name="translationLocale"]').select('pt_BR');
        cy.get('[data-cy="create-translation-modal"] button:contains("Create")').click();
        cy.get('[data-cy="create-translation-modal"]').should('not.exist');

        cy.get('[data-cy="create-translation"]').click();
        cy.get('[data-cy="create-translation-modal"]')
            .contains('Translations have already been created for this submission in all languages available in this journal');
        cy.logout();
    });
    it('Access new translation and updates its title to portuguese', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.openSubmissionInLocale(submissionData.title['en'], 'en');
        cy.openTranslation('Portuguese (Brazil)');

        cy.updateTranslationTitleAbstract('pt_BR', submissionData.title['pt_BR'], submissionData.abstract['pt_BR']);

        cy.logout();
    });
    it('Reference to translated submission on translation submission workflow', function () {
        cy.findSubmissionAsEditor('dbarnes', null, submissionData.title['fr_CA']);

        cy.get('[data-cy="doi-for-translation-actions"] a:contains("Translated submission")').click();
        cy.get('[data-cy="translation-list"]').should('exist');
        cy.get('[data-cy="active-modal"]').contains(submissionData.title['en']);
    });
});
