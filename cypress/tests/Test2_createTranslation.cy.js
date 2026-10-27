import '../support/commands.js';

describe('DOI For Translation - Creation of submission translation', function () {
    let submissionData;

    before(function() {
        submissionData = {
            'id' : 0,
            'section': 'Articles',
            'title': {
                'en': 'The principles of XP',
                'fr_CA': 'Les principes de XP',
            },
			'abstract': {
                'en': 'Just a simple abstract',
                'fr_CA': 'Juste un simple résumé'
            },
			'keywords': ['plugin', 'testing'],
            'files': [
                {
                    'file': 'dummy.pdf',
                    'fileName': 'dummy.pdf',
                    'mimeType': 'application/pdf',
                    'genre': 'Article Text'
                }
            ]
		}
    });

    function beginSubmission() {
        cy.get('label:contains("English")').click();
        cy.setTinyMceContent('startSubmission-title-control', submissionData.title['en']);
        cy.get('label:contains("' + submissionData.section + '")').click();
        cy.get('input[name="submissionRequirements"]').check();
        cy.get('input[name="privacyConsent"]').check();
        cy.contains('button', 'Begin Submission').click();
    }

    function detailsStep() {
        cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract['en']);
        submissionData.keywords.forEach(keyword => {
            cy.get('#titleAbstract-keywords-control-en').type(keyword, {delay: 0});
            cy.get('#titleAbstract-keywords-control-en').type('{enter}', {delay: 0});
        });
        cy.contains('button', 'Continue').click();
    }

    function filesStep() {
        cy.uploadSubmissionFiles(submissionData.files);
        cy.contains('button', 'Continue').click();
    }

    it('Author creates new submission', function () {
        cy.login('cmontgomerie', null, 'publicknowledge');
        cy.get('a:contains("New Submission")').first().click();

        beginSubmission();
        detailsStep();
        filesStep();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Submit').click();
        cy.get('div[role=dialog]').within(() => {
            cy.contains('button', 'Submit').click();
        });

		cy.contains('Submission complete');
		cy.get('a:contains("Review this submission")').click();
        cy.get('[data-cy="active-modal"] p:contains("' + submissionData.title['en'] + '")');

        cy.get('[data-cy="doi-for-translation-actions"]').should('not.exist');

        cy.logout();
    });
    it('Editor creates translation of a submission', function() {
        cy.findSubmissionAsEditor('dbarnes', null, submissionData.title['en']);
        cy.location('search').then(search => {
            submissionData.id = parseInt(new URLSearchParams(search).get('workflowSubmissionId'));
        });

        cy.get('[data-cy="create-translation"]').click();
        cy.get('[data-cy="create-translation-modal"]').within(() => {
            cy.contains('label', 'Translation language');
            cy.contains('Choose the primary language of the new submission');
            cy.get('select[name="translationLocale"]').select('fr_CA');
            cy.contains('button', 'Create').click();
        });
        cy.get('[data-cy="create-translation-modal"]').should('not.exist');

        cy.logout();
    });
    it('Access translation submission and updates title', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.openSubmissionInLocale(submissionData.title['en'], 'en');
        cy.openTranslation('French (Canada)');

        cy.location('search').should(search => {
            const translationSubmissionId = parseInt(new URLSearchParams(search).get('workflowSubmissionId'));
            expect(translationSubmissionId).not.to.equal(submissionData.id);
        });
        cy.get('[data-cy="doi-for-translation-actions"]').contains('Translated submission');
        cy.get('[data-cy="create-translation"]').should('not.exist');

        cy.updateTranslationTitleAbstract('fr_CA', submissionData.title['fr_CA'], submissionData.abstract['fr_CA']);
    });
});
