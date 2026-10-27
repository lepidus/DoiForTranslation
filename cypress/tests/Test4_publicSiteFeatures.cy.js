import '../support/commands.js';

function publishSubmission(hasAuthorUser = false) {
    cy.clickDecision('Accept and Skip Review');
    if (hasAuthorUser) {
        cy.waitForEmailTemplateToBeLoaded('Notify Authors');
        cy.get('.decision__footer button').contains('Continue').click();
    }
    cy.recordDecision('has been sent to the copyediting stage');
    cy.isActiveStageTab('Copyediting');

    cy.publish('1', 'Vol. 1 No. 2 (2014)');
}

function visitArticle(title) {
    cy.visit('index.php/publicknowledge/en');
    cy.get('.title a:contains("' + title + '")').click();
}

describe('DOI For Translation - Public site features', function () {
    let title;

    before(function() {
        title =  {
            'en': 'The principles of XP',
            'fr_CA': 'Les principes de XP',
            'pt_BR': 'Os princípios da XP'
        }
    });

    it('Editor publishes both submissions', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.openSubmissionInLocale(title['en'], 'en');
        publishSubmission(true);

        cy.logout();
        cy.findSubmissionAsEditor('dbarnes', null, title['fr_CA']);
        publishSubmission();

        cy.logout();
        cy.findSubmissionAsEditor('dbarnes', null, title['pt_BR']);
        publishSubmission();

        cy.logout();
    });
    it('List of translations of a submission in landing page', function () {
        visitArticle(title['en']);

        cy.contains('h1', title['en']);
        cy.contains('Translations of this article');
        cy.contains('a', title['fr_CA']).parent().within(() => {
            cy.contains('(French (Canada))');
        });
        cy.contains('a', title['fr_CA']).click();

        cy.contains('h1', title['fr_CA']);
    });
    it('Reference to translated submission on translation submission landing page', function () {
        visitArticle(title['en']);
        cy.contains('a', title['fr_CA']).click();

        cy.contains('h1', title['fr_CA']);
        cy.contains('h2', 'Translation');
        cy.contains('This article is a translation in French (Canada) of the article:');
        cy.contains('a', title['en']).click();

        cy.contains('h1', title['en']);
    });
    it('References in article summaries', function() {
        cy.visit('index.php/publicknowledge/en');

        cy.get('.title a:contains("' + title['en'] + '")')
        .parent().parent().within(() => {
            cy.contains('div', 'Translations of this article:').within(() => {
                cy.contains('a', 'French (Canada)');
                cy.contains('a', 'Portuguese (Brazil)');
            });
        });

        cy.visit('index.php/publicknowledge/pt_BR');
        cy.get('.title a:contains("' + title['pt_BR'] + '")')
        .parent().parent().within(() => {
            cy.contains('div', 'Este artigo é uma tradução em português (Brasil) do artigo:').within(() => {
                cy.contains('a', title['en']);
            });
        });
    });
    it('Summary article count is stable across navigation locales', function() {
        cy.visit('index.php/publicknowledge/en');
        cy.get('.cmp_article_list .obj_article_summary').its('length').then((enCount) => {
            cy.get('.title a:contains("' + title['en'] + '")').should('have.length', 1);
            cy.get('.title a:contains("' + title['fr_CA'] + '")').should('have.length', 0);
            cy.get('.title a:contains("' + title['pt_BR'] + '")').should('have.length', 0);

            cy.visit('index.php/publicknowledge/pt_BR');
            cy.get('.cmp_article_list .obj_article_summary').should('have.length', enCount);
            cy.get('.title a:contains("' + title['pt_BR'] + '")').should('have.length', 1);
            cy.get('.title a:contains("' + title['en'] + '")').should('have.length', 0);
            cy.get('.title a:contains("' + title['fr_CA'] + '")').should('have.length', 0);

            cy.visit('index.php/publicknowledge/fr_CA');
            cy.get('.cmp_article_list .obj_article_summary').should('have.length', enCount);
            cy.get('.title a:contains("' + title['fr_CA'] + '")').should('have.length', 1);
            cy.get('.title a:contains("' + title['en'] + '")').should('have.length', 0);
            cy.get('.title a:contains("' + title['pt_BR'] + '")').should('have.length', 0);
        });
    });
});
