export type QuestionAuthor = 'parent' | 'staff' | 'curator';
export type QuestionDelivery = 'sending' | 'delivered' | 'failed';

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

export interface QuestionProblem {
  status: number | null;
  code: string;
  network: boolean;
}
