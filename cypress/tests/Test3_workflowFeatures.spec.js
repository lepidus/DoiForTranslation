import '../support/commands.js';

describe('DOI For Translation - Workflow features', function () {
    let submissionData;

    before(function() {
        submissionData = {
            'title': {
                'en_US': 'The principles of XP',
                'fr_CA': 'Les principes de XP',
				'pt_BR': 'Os princípios da XP'
            },
			'abstract': {
                'en_US': 'Just a simple abstract',
                'fr_CA': 'Juste un simple résumé',
				'pt_BR': 'Apenas um simples resumo'
            },
		}
    });
    it('List of translations of a submission', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title['en_US']);

        cy.contains('Translations').click();
        cy.get('.pkpPublication__translations li a').should('have.length', 1);
        cy.get('.pkpPublication__translations a:contains("Français (Canada)")').click();

        cy.contains(submissionData.title['fr_CA']);
    });
    it('Locales are hidden from create translation form', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title['en_US']);

        cy.get('button:contains("Create translation")').click();
        cy.get('select[name="translationLocale"] option[value="en_US"]').should('not.exist');
        cy.get('select[name="translationLocale"] option[value="fr_CA"]').should('not.exist');
        cy.get('select[name="translationLocale"]').select('pt_BR');
        cy.get('#createTranslationModal button:contains("Create")').click();
        cy.waitJQuery();

        cy.get('button:contains("Create translation")').click();
        cy.contains('Translations have already been created for this submission in all languages available in this journal');
    });
    it('Access new translation and updates its title to portuguese', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title['en_US']);

        cy.get('#publication-button').click();
        cy.get('button:visible:contains("Português (Brasil)")').click();
        
        cy.get('input[name="title-en_US"]').clear();
        cy.setTinyMceContent('titleAbstract-abstract-control-en_US', '');
        cy.get('#titleAbstract-abstract-control-en_US').click();
        
        cy.get('input[name="title-pt_BR"]').clear().type(submissionData.title['pt_BR'], { delay: 0 });
        cy.setTinyMceContent('titleAbstract-abstract-control-pt_BR', submissionData.abstract['pt_BR']);
        cy.get('#titleAbstract-abstract-control-pt_BR').click();
        
        cy.get('#titleAbstract button:contains("Save")').click();
        cy.get('#titleAbstract span[role="status"]').contains('Saved');
        cy.logout();
    });
    it('Reference to translated submission on translation submission workflow', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title['fr_CA']);

        cy.contains('Translated submission').click();
        
    });
});