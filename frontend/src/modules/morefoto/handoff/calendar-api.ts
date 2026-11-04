import api from '@/api/http';

export interface GroupCalendarView {
  groupId: string;
  revision: number;
  calendar: {
    timezone: string;
    sentAt: string | null;
    closesAt: string | null;
    digitalSalesUntil: string | null;
    deliveryDueAt: string | null;
    status: 'preparing' | 'open' | 'digital' | 'closed';
  };
  history: {
    actorId: number;
    actorName: string;
    at: string;
    days: number | null;
    before: GroupCalendarView['calendar'];
    after: GroupCalendarView['calendar'];
  }[];
}

const path = (groupId: string) => '/api/v1/groups/' + encodeURIComponent(groupId);

export const calendarApi = {
  async get(groupId: string): Promise<GroupCalendarView> {
    return (await api.get<GroupCalendarView>(path(groupId) + '/calendar')).data;
  },
  async extend(groupId: string, revision: number, days: number, requestId: string): Promise<GroupCalendarView> {
    return (
      await api.post<GroupCalendarView>(
        path(groupId) + '/calendar-extensions',
        { revision, days, confirmed: true },
        { headers: { 'Idempotency-Key': requestId.replace(/-/g, '') } }
      )
    ).data;
  }
};
