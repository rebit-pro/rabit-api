import api from './http';
import type { StaffRole } from '@/modules/morefoto/types';

export interface GeeTestCaptchaPayload {
  lot_number: string;
  captcha_output: string;
  pass_token: string;
  gen_time: string;
}

export interface LoginRequest {
  email: string;
  password: string;
  captcha?: GeeTestCaptchaPayload;
}

export interface RegisterRequest {
  email: string;
  password: string;
}

export interface RequestRegistrationCodeResponse {
  email: string;
  codeExpiresAt: string;
  resendAvailableAt: string;
}

export interface ConfirmRegistrationRequest {
  email: string;
  code: string;
}

/** Versioned avatar addresses from the API; without them the client draws initials. */
export interface AvatarRef {
  version: number;
  thumbUrl: string;
  fullUrl: string;
}

/** The organizer's contact for help with access (DS-12); any part may be missing. */
export interface SupportContact {
  name: string | null;
  email: string | null;
  phone: string | null;
}

export interface AuthUser {
  role?: StaffRole;
  permissions?: string[];
  accessRevision?: number;
  id: number;
  email: string;
  name: string;
  avatar?: AvatarRef | null;
  support?: SupportContact | null;
}

export interface LoginResponse {
  token: string;
  expiresAt: string;
  user: AuthUser;
}

export interface StaffProfile extends AuthUser {
  role: StaffRole;
  active: boolean;
  permissions: string[];
  accessRevision: number;
}

export interface InvitationPreview {
  maskedEmail: string;
  name: string;
  expiresAt: string;
}

const link = (token: string) => encodeURIComponent(token);

export const accessApi = {
  invitation(token: string): Promise<InvitationPreview> {
    return api.get('/api/v1/auth/invitations/' + link(token)).then((r) => r.data);
  },
  acceptInvitation(token: string, password: string): Promise<LoginResponse> {
    return api.post('/api/v1/auth/invitations/' + link(token) + '/accept', { password }).then((r) => r.data);
  },
  requestPasswordReset(email: string): Promise<void> {
    return api.post('/api/v1/auth/password-resets', { email }).then(() => undefined);
  },
  confirmPasswordReset(token: string, password: string): Promise<LoginResponse> {
    return api.post('/api/v1/auth/password-resets/' + link(token) + '/confirm', { password }).then((r) => r.data);
  },
  changePassword(currentPassword: string, newPassword: string): Promise<void> {
    return api.patch('/api/v1/me/password', { currentPassword, newPassword }).then(() => undefined);
  }
};

export const authApi = {
  me(): Promise<StaffProfile> {
    return api.get('/api/v1/me').then((r) => r.data);
  },
  login(data: LoginRequest): Promise<LoginResponse> {
    return api.post('/api/v1/auth/login', data).then((r) => r.data);
  },

  requestRegistrationCode(data: RegisterRequest): Promise<RequestRegistrationCodeResponse> {
    return api.post('/api/v1/auth/register/request-code', data).then((r) => r.data);
  },

  confirmRegistration(data: ConfirmRegistrationRequest): Promise<LoginResponse> {
    return api.post('/api/v1/auth/register/confirm', data).then((r) => r.data);
  },

  logout(): Promise<void> {
    return api.post('/api/v1/auth/logout');
  }
};
