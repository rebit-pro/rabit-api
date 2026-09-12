import type { AuthUser } from '@/api/auth';
import type { Institution, Group, StaffRole } from '../types';

export interface DemoAccount extends AuthUser {
  role: StaffRole;
  institutionIds: string[];
  groupIds: string[];
}

export const demoPassword = 'morefoto-demo';
export const demoAccounts: DemoAccount[] = [
  {
    id: 101,
    email: 'organizer@morefoto.test',
    name: 'Анна',
    role: 'organizer',
    institutionIds: [],
    groupIds: []
  },
  {
    id: 102,
    email: 'curator@morefoto.test',
    name: 'Рита',
    role: 'curator',
    institutionIds: ['sun'],
    groupIds: []
  },
  {
    id: 103,
    email: 'head@morefoto.test',
    name: 'Ирина',
    role: 'head',
    institutionIds: ['sun'],
    groupIds: []
  },
  {
    id: 104,
    email: 'teacher@morefoto.test',
    name: 'Мария',
    role: 'teacher',
    institutionIds: ['sun'],
    groupIds: ['sun-stars']
  },
  {
    id: 105,
    email: 'empty@morefoto.test',
    name: 'Новый куратор',
    role: 'curator',
    institutionIds: [],
    groupIds: []
  }
];

export const institutions: Institution[] = [
  {
    id: 'sun',
    name: 'Детский сад «Солнечный»',
    address: 'Воронеж, Учебная улица, 12'
  },
  {
    id: 'school',
    name: 'Школа · тестовая подборка',
    address: 'Учебный адрес — уточняется перед заказом'
  },
  {
    id: 'rainbow',
    name: 'Детский сад «Радуга»',
    address: 'Воронеж, Примерная улица, 7'
  }
];

export const groups: Group[] = [
  {
    id: 'school-1a',
    institutionId: 'school',
    name: '1 «А» · тестовый класс',
    shootName: 'Лето в кадре',
    kind: 'regular',
    state: 'open',
    closesAt: '2026-09-12T18:00:00+03:00'
  },
  {
    id: 'sun-stars',
    institutionId: 'sun',
    name: 'Звёздочки',
    shootName: 'Лето в кадре',
    kind: 'regular',
    state: 'open',
    closesAt: '2026-09-12T18:00:00+03:00'
  },
  {
    id: 'sun-bees',
    institutionId: 'sun',
    name: 'Пчёлки',
    shootName: 'Осенняя съёмка · 2026',
    kind: 'regular',
    state: 'preparing',
    closesAt: null
  },
  {
    id: 'sun-staff',
    institutionId: 'sun',
    name: 'Сотрудники',
    shootName: 'Осенняя съёмка · 2026',
    kind: 'staff',
    state: 'open',
    closesAt: '2026-09-12T18:00:00+03:00'
  },
  {
    id: 'rainbow-clouds',
    institutionId: 'rainbow',
    name: 'Облачка',
    shootName: 'Осенняя съёмка · 2026',
    kind: 'regular',
    state: 'preparing',
    closesAt: null
  }
];
