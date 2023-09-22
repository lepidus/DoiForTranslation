describe('Author Version - Creation of submission translation', function () {
    let submissionData;
    
    before(function() {
        submissionData = {
            'title': 'Testing plugin for creating translation of submissions',
			'abstract': 'Just a simple abstract',
			'keywords': ['plugin', 'testing']
		}
    });

    function step1() {
        cy.get('select[id="locale"]').select('en_US');
        cy.get('select[id="sectionId"]').select('Articles');
        cy.get('input[id^="checklist-"]').click({ multiple: true });
		cy.get('input[id=privacyConsent]').click();
        cy.get('#submitStep1Form button.submitFormButton').click();
    }

    function step2() {
        cy.get('#submitStep2Form button.submitFormButton').click();
    }

    function step3() {
        cy.get('input[name^="title"]').first().type(submissionData.title, { delay: 0 });
        cy.get('label').contains('Title').click();
        cy.get('textarea[id^="abstract-"').then((node) => {
            cy.setTinyMceContent(node.attr("id"), submissionData.abstract);
        });
        cy.get('.section > label:visible').first().click();
        cy.get('ul[id^="en_US-keywords-"]').then(node => {
            node.tagit('createTag', submissionData.keywords[0]);
            node.tagit('createTag', submissionData.keywords[1]);
        });

        cy.get('#submitStep3Form button.submitFormButton').click();
    }

    function step4() {
        cy.waitJQuery();
		cy.get('#submitStep4Form button.submitFormButton').click();
		cy.get('button.pkpModalConfirmButton').click();
    }
    
    it('Author creates new submission', function () {
        cy.login('cmontgomerie', null, 'publicknowledge');
		cy.get('div#myQueue a:contains("New Submission")').click();

        step1();
        step2();
        step3();
        step4();

        cy.waitJQuery();
		cy.get('h2:contains("Submission complete")');
		cy.get('a:contains("Review this submission")').click();

        cy.get('button:contains("Create translation")').should('not.exist');
        
        cy.logout();
    });
    it('Editor creates translation of a submission', function() {
        cy.findSubmissionAsEditor('dbarnes', null, 'Montgomerie');
        cy.get('#publication-button').click();

        cy.get('button:contains("Create translation")').click();
        cy.get('label:contains("Translation language")');
        cy.contains('Choose the primary language of the new submission');
        cy.get('select[name="translationLocale"]').select('fr_CA');
        cy.get('div[modalname="createTranslation"] button:contains("Create")').click();
    });
});