import React, { createContext, useCallback, useMemo, useState } from 'react';
import { ThemeProvider as SCThemeProvider } from 'styled-components/macro';
import { deepmerge } from 'deepmerge-ts';
import type { DeepPartial } from 'ts-essentials';
import { defaultTheme } from '@/theme/defaults';
import type { NookTheme } from '@/theme/types';

export interface ThemeContextValue {
    theme: NookTheme;
    setTheme: (theme: DeepPartial<NookTheme>) => void;
}

export const ThemeContext = createContext<ThemeContextValue>({
    theme: defaultTheme,
    // eslint-disable-next-line @typescript-eslint/no-empty-function
    setTheme: () => {},
});

interface Props {
    theme?: DeepPartial<NookTheme>;
    children: React.ReactNode;
}

const NookThemeProvider: React.FC<Props> = ({ theme: customTheme, children }) => {
    const [override, setOverride] = useState<DeepPartial<NookTheme> | undefined>(customTheme);

    const merged = useMemo<NookTheme>(() => {
        return deepmerge(defaultTheme, override || {}) as NookTheme;
    }, [override]);

    const setTheme = useCallback((next: DeepPartial<NookTheme>) => {
        setOverride((prev) => deepmerge(prev || {}, next) as DeepPartial<NookTheme>);
    }, []);

    const value = useMemo<ThemeContextValue>(() => ({ theme: merged, setTheme }), [merged, setTheme]);

    return (
        <ThemeContext.Provider value={value}>
            <SCThemeProvider theme={merged}>{children}</SCThemeProvider>
        </ThemeContext.Provider>
    );
};

export { NookThemeProvider };
