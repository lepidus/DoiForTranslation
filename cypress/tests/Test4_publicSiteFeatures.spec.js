import '../support/commands.js';

function publishSubmission() {
    cy.recordEditorialDecision('Accept and Skip Review');
    cy.get('li.ui-state-active a:contains("Copyediting")');
    cy.get('#publication-button').click();
    cy.get('button:contains("Schedule For Publication")').click();
    cy.waitJQuery();
    
    cy.get('#assignToIssue-issueId-control').select('1');
    cy.get('div[id^="assign-"] button:contains("Save")').click();
    cy.waitJQuery();
    
    cy.get('button:contains("Publish")').click();
}

function assignMyselfAsJournalEditor() {
    cy.get('a:contains("Assign")').click();
    cy.get('tr[id^="component-grid-users-userselect-userselectgrid-row"] > .first_column > input').first().click();
    cy.get("#addParticipantForm > .formButtons > .submitFormButton").click();
    cy.waitJQuery();
}

describe('DOI For Translation - Public site features', function () {
    let title;

    before(function() {
        title =  {
            'en_US': 'The principles of XP',
            'fr_CA': 'Les principes de XP',
            'pt_BR': 'Os princípios da XP'
        }
    });
    
    it('Editor publishes both submissions', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', title['en_US']);
        publishSubmission();

        cy.get('a:contains("Submissions")').click();
        cy.findSubmission('active', title['fr_CA']);
        assignMyselfAsJournalEditor();
        publishSubmission();

        cy.get('a:contains("Submissions")').click();
        cy.findSubmission('active', title['pt_BR']);
        assignMyselfAsJournalEditor();
        publishSubmission();
    });
    it('List of translations of a submission in landing page', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('archive', title['en_US']);

        cy.get('.pkpHeader__actions a:contains("View")').click();
        
        cy.contains('h1', title['en_US']);
        cy.scrollTo('bottom');
        cy.contains('Translations of this article');
        cy.contains('a', title['fr_CA']).parent().within(() => {
            cy.contains('(Français (Canada))');
        });
        cy.contains('a', title['fr_CA']).click();

        cy.contains('h1', title['fr_CA']);
    });
    it('Reference to translated submission on translation submission landing page', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('archive', title['fr_CA']);

        cy.get('.pkpHeader__actions a:contains("View")').click();
        
        cy.contains('h1', title['fr_CA']);
        cy.scrollTo('bottom');
        cy.contains('h2', 'Translation');
        cy.contains('This article is a translation in Français (Canada) of the article:');
        cy.contains('a', title['en_US']).click();

        cy.contains('h1', title['en_US']);
    });
    it('References in article summaries', function() {
        cy.visit('');

        cy.get('.title a:contains("' + title['en_US'] + '")')
        .parent().parent().within(() => {
            cy.contains('div', 'Translations of this article:').within(() => {
                cy.contains('a', 'Français (Canada)');
                cy.contains('a', 'Português (Brasil)');
            });
        });

        cy.get('.title a:contains("' + title['fr_CA'] + '")')
        .parent().parent().within(() => {
            cy.contains('div', 'This article is a translation in Français (Canada) of the article:').within(() => {
                cy.contains('a', title['en_US']);
            });
        });

        cy.get('.title a:contains("' + title['pt_BR'] + '")')
        .parent().parent().within(() => {
            cy.contains('div', 'This article is a translation in Português (Brasil) of the article:').within(() => {
                cy.contains('a', title['en_US']);
            });
        });
    });
});