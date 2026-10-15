export type ScheduleRecurrence = {
    frequency?: 'weekly' | 'monthly' | 'yearly';
    day_of_week?: number;
    monthly_rule?:
        | 'weekday_occurrence'
        | 'day_of_month'
        | 'business_day'
        | 'business_day_after';
    monthly_ordinal?: 'first' | 'second' | 'third' | 'fourth' | 'last';
    monthly_day?: number;
    monthly_business_position?: 'first' | 'last';
    monthly_after_day?: number;
    monthly_after_position?: 'first' | 'last';
    yearly_month?: number;
    yearly_day?: number;
};

const businessDaysInMonth = (
    year: number,
    month: number,
    afterDay = 0,
): Date[] => {
    const lastDay = new Date(year, month + 1, 0).getDate();

    return Array.from({ length: lastDay - afterDay }, (_, index) =>
        new Date(year, month, afterDay + index + 1),
    ).filter((date) => date.getDay() !== 0 && date.getDay() !== 6);
};

export const scheduleOccursOn = (
    schedule: ScheduleRecurrence,
    date: Date,
): boolean => {
    const frequency = schedule.frequency ?? 'weekly';

    if (frequency === 'weekly') {
        return Number(schedule.day_of_week ?? 0) === date.getDay();
    }

    if (frequency === 'yearly') {
        return (
            Number(schedule.yearly_month) === date.getMonth() + 1 &&
            Number(schedule.yearly_day) === date.getDate()
        );
    }

    const monthlyRule = schedule.monthly_rule ?? 'weekday_occurrence';

    if (monthlyRule === 'weekday_occurrence') {
        if (Number(schedule.day_of_week ?? 0) !== date.getDay()) {
            return false;
        }

        if (schedule.monthly_ordinal === 'last') {
            return new Date(
                date.getFullYear(),
                date.getMonth(),
                date.getDate() + 7,
            ).getMonth() !== date.getMonth();
        }

        const ordinal = {
            first: 1,
            second: 2,
            third: 3,
            fourth: 4,
        }[schedule.monthly_ordinal ?? 'first'];

        return Math.floor((date.getDate() - 1) / 7) + 1 === ordinal;
    }

    if (monthlyRule === 'day_of_month') {
        return Number(schedule.monthly_day) === date.getDate();
    }

    const businessDays = businessDaysInMonth(date.getFullYear(), date.getMonth());

    if (monthlyRule === 'business_day') {
        const businessDay = schedule.monthly_business_position === 'last'
            ? businessDays.at(-1)
            : businessDays[0];

        return businessDay?.getDate() === date.getDate();
    }

    const daysAfterReference = businessDaysInMonth(
        date.getFullYear(),
        date.getMonth(),
        Number(schedule.monthly_after_day ?? 1),
    );
    const businessDay = schedule.monthly_after_position === 'last'
        ? daysAfterReference.at(-1)
        : daysAfterReference[0];

    return businessDay?.getDate() === date.getDate();
};