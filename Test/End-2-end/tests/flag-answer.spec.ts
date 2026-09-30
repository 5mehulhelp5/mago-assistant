/*
 * Copyright © Mago Assistant
 */

import {expect, test} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import ChatPanel from 'Pages/backend/ChatPanel';
import FlaggedAnswers from 'Pages/backend/FlaggedAnswers';
import {PROVIDER_ROUND_TRIP_TIMEOUT} from 'Config/timeouts';

const chatPanel = new ChatPanel();
const flaggedAnswers = new FlaggedAnswers();

const QUESTION = 'Please run the E2E Flag Answer Check.';
const ANSWER = 'Fourteen orders are on hold.';

/**
 * Flagging goes through the real backend: the answer has to be stored for there to be a message id
 * to flag, and the flag has to be read back from the database on the Flagged Answers screen. Only
 * the provider is mocked, by WireMock (wiremock/mappings/flag-answer.json).
 */
test.describe('Flag an answer', () => {
  /* The Flagged Answers grid keeps its keyword search per admin, and every spec shares one admin,
     so a search in one test would empty the grid another test borrows a view link from. */
  test.describe.configure({mode: 'serial'});

  test('Keeps the flagged answer and lets an admin resolve, download and delete it', async ({page}) => {
    const note = 'It said fourteen, there were nine ' + Date.now();

    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, QUESTION);
    await expect(chatPanel.lastAssistantMessage(page)).toContainText(ANSWER, {timeout: PROVIDER_ROUND_TRIP_TIMEOUT});

    const flagId = await chatPanel.flagLastAnswer(page, note);

    await expect(chatPanel.flagLine(page)).toContainText('Flagged.');
    await expect(chatPanel.flagButton(page)).toHaveAttribute('aria-pressed', 'true');

    await flaggedAnswers.openFlag(page, flagId);

    await expect(flaggedAnswers.content(page)).toContainText(ANSWER);
    await expect(flaggedAnswers.content(page)).toContainText(QUESTION);
    await expect(flaggedAnswers.content(page)).toContainText(note);
    await expect(flaggedAnswers.status(page)).toHaveText(/open/i);

    await flaggedAnswers.resolve(page);

    await expect(flaggedAnswers.successMessage(page)).toContainText('Flag marked as resolved.');
    await expect(flaggedAnswers.status(page)).toHaveText(/resolved/i);

    await flaggedAnswers.reopen(page);

    await expect(flaggedAnswers.successMessage(page)).toContainText('Flag reopened.');
    await expect(flaggedAnswers.status(page)).toHaveText(/open/i);

    const download = await flaggedAnswers.download(page);
    const bundle = JSON.parse(await readFile(await download.path(), 'utf8'));

    expect(download.suggestedFilename()).toBe('mago-flag-' + flagId + '.json');
    expect(bundle.flag.note).toBe(note);
    expect(bundle.snapshot.answer.content).toBe(ANSWER);
    expect(bundle.snapshot.usage.calls.length, 'the provider call of the turn is part of the flag').toBeGreaterThan(0);

    const flagUrl = page.url();

    await flaggedAnswers.delete(page);

    await expect(flaggedAnswers.successMessage(page)).toContainText('The flag was deleted.');

    await page.goto(flagUrl, {waitUntil: 'load'});

    await expect(flaggedAnswers.errorMessage(page)).toContainText('This flagged answer no longer exists.');
  });

  test('Deletes every flag the grid shows with Select All and the mass action', async ({page}) => {
    const keyword = 'e2emassdelete' + Date.now();

    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, QUESTION);
    await expect(chatPanel.lastAssistantMessage(page)).toContainText(ANSWER, {timeout: PROVIDER_ROUND_TRIP_TIMEOUT});
    await chatPanel.flagLastAnswer(page, 'Mass delete ' + keyword);
    await flaggedAnswers.openGrid(page);
    await flaggedAnswers.search(page, keyword);
    await expect(flaggedAnswers.gridRows(page)).toHaveCount(1);

    await flaggedAnswers.deleteAllMatching(page);

    await expect(flaggedAnswers.successMessage(page)).toContainText('1 flag(s) were deleted.');
    await flaggedAnswers.search(page, keyword);
    await expect(flaggedAnswers.gridRows(page)).toHaveCount(0);
    await flaggedAnswers.clearSearch(page);
  });

  test('Lets the admin who flagged an answer take the flag back from the panel', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, QUESTION);
    await expect(chatPanel.lastAssistantMessage(page)).toContainText(ANSWER, {timeout: PROVIDER_ROUND_TRIP_TIMEOUT});
    await chatPanel.flagLastAnswer(page, 'Flagged by mistake');
    await expect(chatPanel.flagLine(page)).toContainText('Flagged.');

    await chatPanel.flagButton(page).click();

    await expect(chatPanel.flagLine(page)).toContainText('Flag removed.');
    await expect(chatPanel.flagButton(page)).toHaveAttribute('aria-pressed', 'false');
  });
});
