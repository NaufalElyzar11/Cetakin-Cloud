import { useEffect, useRef, useState } from 'react';

type Errors = Record<string, string | string[] | undefined>;

export function useAccessFeedback() {
    const [failure, setFailure] = useState('');

    return {
        failure,
        clearFailure: () => setFailure(''),
        onHttpException: ({ status }: { status: number }) => {
            setFailure(
                status === 419
                    ? 'Your session has expired. Reload this page before trying again.'
                    : status === 429
                      ? 'Too many attempts. Please wait before trying again.'
                      : 'The request could not be confirmed. Please try again.',
            );
            return false;
        },
        onNetworkError: () => {
            setFailure(
                'The request could not be confirmed. Check your connection before trying again.',
            );
            return false;
        },
    };
}

export default function FormFeedback({
    errors,
    failure,
}: {
    errors: Errors;
    failure: string;
}) {
    const messages = Object.values(errors)
        .flat()
        .filter((message): message is string => Boolean(message));
    if (failure) messages.push(failure);
    const signature = messages.join('\n');
    const summary = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (signature) summary.current?.focus();
    }, [signature]);

    if (messages.length === 0) return null;

    return (
        <div
            className="access-errors"
            role="alert"
            aria-label="Request not completed"
            tabIndex={-1}
            ref={summary}
        >
            <p>Please review the following:</p>
            <ul>
                {messages.map((message, index) => (
                    <li key={index}>{message}</li>
                ))}
            </ul>
        </div>
    );
}
