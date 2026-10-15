import type { Actor } from '../Access/Account';
import type { SubmittedFile } from '../Intake/types';

export type { Actor };
export type WorkDisposition = 'ACTIONABLE' | 'WAITING' | 'BLOCKED';
export type ReviewSummary = {
    id: string;
    customer: { id: number; name: string; email: string; phone: string };
    submittedAt: string;
    status: 'SUBMITTED' | 'DECLINED';
    material: string;
    color: string;
    quantity: string;
    fileCount: number;
    availableFileCount: number | null;
    reviewDisposition:
        | 'NEEDS_REVIEW'
        | 'INSUFFICIENT'
        | 'SUFFICIENT'
        | 'STALE'
        | 'UNAVAILABLE'
        | 'NEEDS_VERIFICATION'
        | 'TERMINAL';
    workDisposition: WorkDisposition | 'TERMINAL';
    responsible: { id: number; name: string; current: boolean } | null;
    nextAction: string | null;
    detailUrl: string;
};
export type ReviewDetail = ReviewSummary & {
    canReview: boolean;
    notes: string | null;
    files: (Omit<SubmittedFile, 'downloadUrl'> & {
        downloadUrl: string | null;
    })[];
    revision: number;
    basis: string;
    latestReview: {
        complete: boolean;
        materialAvailable: boolean;
        feasible: boolean;
        sufficient: boolean;
        findings: string;
        reviewedAt: string;
    } | null;
    history: {
        kind: string;
        actorName: string;
        occurredAt: string;
        summary: string;
    }[];
    reviewUrl: string;
    assignmentUrl: string;
    declineUrl: string;
};

export const reviewLabels: Record<ReviewSummary['reviewDisposition'], string> =
    {
        NEEDS_REVIEW: 'Needs manual review',
        INSUFFICIENT: 'Review insufficient',
        SUFFICIENT: 'Review sufficient on the current available basis',
        STALE: 'Review basis changed — re-review required',
        UNAVAILABLE: 'Exact files unavailable — review cannot be sufficient',
        NEEDS_VERIFICATION:
            'Recorded review requires current exact-file verification',
        TERMINAL: 'Declined — review no longer eligible',
    };
export const workLabels: Record<ReviewSummary['workDisposition'], string> = {
    ACTIONABLE: 'Actionable',
    WAITING: 'Waiting',
    BLOCKED: 'Blocked',
    TERMINAL: 'Terminal — declined',
};
