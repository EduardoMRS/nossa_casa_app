import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { scheduleOccursOn } from '../../resources/js/lib/scheduleRecurrence.ts';

const brandingEditor = readFileSync(
    new URL('../../resources/js/pages/Admin/Branding.vue', import.meta.url),
    'utf8',
);

const day = (year: number, month: number, date: number): Date =>
    new Date(year, month - 1, date);

test('legacy schedules continue to repeat on their weekday', () => {
    assert.equal(scheduleOccursOn({ day_of_week: 0 }, day(2026, 6, 7)), true);
    assert.equal(scheduleOccursOn({ day_of_week: 0 }, day(2026, 6, 8)), false);
});

test('monthly schedules support weekday ordinals and fixed dates', () => {
    const firstSunday = {
        frequency: 'monthly' as const,
        monthly_rule: 'weekday_occurrence' as const,
        monthly_ordinal: 'first' as const,
        day_of_week: 0,
    };
    const lastSunday = { ...firstSunday, monthly_ordinal: 'last' as const };

    assert.equal(scheduleOccursOn(firstSunday, day(2026, 2, 1)), true);
    assert.equal(scheduleOccursOn(firstSunday, day(2026, 2, 8)), false);
    assert.equal(scheduleOccursOn(lastSunday, day(2026, 2, 22)), true);
    assert.equal(
        scheduleOccursOn(
            { frequency: 'monthly', monthly_rule: 'day_of_month', monthly_day: 31 },
            day(2026, 2, 28),
        ),
        false,
    );
});

test('monthly schedules support first and last business days', () => {
    const first = {
        frequency: 'monthly' as const,
        monthly_rule: 'business_day' as const,
        monthly_business_position: 'first' as const,
    };
    const last = { ...first, monthly_business_position: 'last' as const };

    assert.equal(scheduleOccursOn(first, day(2026, 2, 2)), true);
    assert.equal(scheduleOccursOn(last, day(2026, 2, 27)), true);
    assert.equal(scheduleOccursOn(first, day(2026, 2, 1)), false);
});

test('monthly schedules find first or last business day after a reference date', () => {
    const first = {
        frequency: 'monthly' as const,
        monthly_rule: 'business_day_after' as const,
        monthly_after_day: 15,
        monthly_after_position: 'first' as const,
    };
    const last = { ...first, monthly_after_position: 'last' as const };

    assert.equal(scheduleOccursOn(first, day(2026, 2, 16)), true);
    assert.equal(scheduleOccursOn(last, day(2026, 2, 27)), true);
    assert.equal(scheduleOccursOn(first, day(2026, 2, 17)), false);
});

test('yearly schedules repeat on the selected month and day', () => {
    const schedule = { frequency: 'yearly' as const, yearly_month: 3, yearly_day: 12 };

    assert.equal(scheduleOccursOn(schedule, day(2027, 3, 12)), true);
    assert.equal(scheduleOccursOn(schedule, day(2027, 3, 13)), false);
});

test('branding editor exposes weekly, monthly, and yearly schedule rules', () => {
    assert.match(brandingEditor, /v-model="schedule\.frequency"/);
    assert.match(brandingEditor, /v-model="schedule\.monthly_rule"/);
    assert.match(brandingEditor, /v-model(?:\.number)?="schedule\.monthly_after_day"/);
    assert.match(brandingEditor, /v-model(?:\.number)?="schedule\.yearly_month"/);
    assert.match(brandingEditor, /'weekly', 'monthly', 'yearly'/);
});