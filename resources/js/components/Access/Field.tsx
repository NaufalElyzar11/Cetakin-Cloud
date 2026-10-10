import type { HTMLInputTypeAttribute } from 'react';

export default function Field({
    name,
    label,
    type = 'text',
    value,
    onChange,
    error,
    autoComplete,
    minLength,
    hint,
}: {
    name: string;
    label: string;
    type?: HTMLInputTypeAttribute;
    value: string;
    onChange: (value: string) => void;
    error?: string | string[];
    autoComplete: string;
    minLength?: number;
    hint?: string;
}) {
    return (
        <div className="access-field">
            <label htmlFor={name}>{label}</label>
            <input
                id={name}
                name={name}
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                autoComplete={autoComplete}
                minLength={minLength}
                required
                aria-invalid={error ? true : undefined}
                aria-describedby={
                    [hint ? `${name}-hint` : '', error ? `${name}-error` : '']
                        .filter(Boolean)
                        .join(' ') || undefined
                }
            />
            {hint && <p id={`${name}-hint`}>{hint}</p>}
            {error && (
                <p id={`${name}-error`} className="access-field-error">
                    {Array.isArray(error) ? error.join(' ') : error}
                </p>
            )}
        </div>
    );
}
