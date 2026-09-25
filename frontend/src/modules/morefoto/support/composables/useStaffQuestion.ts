import { ref } from 'vue';
import { questionsApi } from '../api';
import { useQuestionThread } from './useQuestionThread';

/** The staff member's single conversation with the curators, polled while the cabinet page is open. */
export function useStaffQuestion() {
  return useQuestionThread(
    { load: () => questionsApi.mine(), send: (text, requestId) => questionsApi.addMine(text, requestId) },
    ref(true)
  );
}
