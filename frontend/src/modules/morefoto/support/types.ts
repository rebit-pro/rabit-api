import type { RequestProblem } from '../../../api/outcome.ts';

export type QuestionAuthor = 'parent' | 'staff' | 'curator';
export type QuestionDelivery = 'sending' | 'delivered' | 'failed' | 'unknown';

export interface QuestionMessage {
  id: number;
  author: QuestionAuthor;
  authorName: string;
  text: string;
  createdAt: string;
  /** Only the author's own replies carry a delivery state; curator answers have null. */
  delivery: QuestionDelivery | null;
}

export interface Question {
  id: number | null;
  number: number | null;
  messages: QuestionMessage[];
}

export interface CreatedQuestion extends Question {
  id: number;
  number: number;
  questionKey: string;
}

export type QuestionProblem = RequestProblem;

/** The first question sent but not yet confirmed: kept until the server returns its questionKey. */
export interface PendingAsk {
  name: string;
  text: string;
  requestId: string;
}
