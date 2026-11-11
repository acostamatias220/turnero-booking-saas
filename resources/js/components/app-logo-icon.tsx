import { SVGAttributes } from 'react';

/** Calendar with a check mark: the Turnero mark. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            xmlns="http://www.w3.org/2000/svg"
            {...props}
        >
            <rect x="3.5" y="5" width="17" height="15.5" rx="3" />
            <path d="M3.5 10h17M8 2.75v4M16 2.75v4" />
            <path d="m9 15 2.2 2.2L15.5 13" />
        </svg>
    );
}
