import React from 'react';
import Icon from '@/components/elements/Icon';
import { IconDefinition } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import styles from './style.module.css';
import useFitText from 'use-fit-text';
import CopyOnClick from '@/components/elements/CopyOnClick';
import styled from 'styled-components/macro';

interface StatBlockProps {
    title: string;
    copyOnClick?: string;
    color?: string | undefined;
    icon: IconDefinition;
    children: React.ReactNode;
    className?: string;
}

const Card = styled.div<{ $accent?: string }>`
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    border-radius: 12px;
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.85) 0%, rgba(15, 23, 42, 0.9) 100%);
    border: 1px solid rgba(129, 140, 248, 0.12);
    box-shadow: 0 4px 12px -6px rgba(0, 0, 0, 0.3);
    transition: all 0.2s ease;
    min-width: 0;

    &:hover {
        border-color: rgba(129, 140, 248, 0.25);
        transform: translateY(-1px);
        box-shadow: 0 8px 18px -8px rgba(99, 102, 241, 0.3);
    }

    &::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: ${(p) => {
            const c = p.$accent;
            if (c === 'bg-red-500') return 'linear-gradient(90deg, #ef4444, #f87171)';
            if (c === 'bg-yellow-500') return 'linear-gradient(90deg, #f59e0b, #fbbf24)';
            return 'linear-gradient(90deg, #6366f1, #818cf8)';
        }};
    }

    @media (max-width: 480px) {
        padding: 10px;
        gap: 8px;
    }
`;

const IconBox = styled.div<{ $accent?: string }>`
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: ${(p) => {
        const c = p.$accent;
        if (c === 'bg-red-500') return 'rgba(239, 68, 68, 0.15)';
        if (c === 'bg-yellow-500') return 'rgba(245, 158, 11, 0.15)';
        return 'rgba(99, 102, 241, 0.15)';
    }};
    color: ${(p) => {
        const c = p.$accent;
        if (c === 'bg-red-500') return '#f87171';
        if (c === 'bg-yellow-500') return '#fbbf24';
        return '#818cf8';
    }};

    & > svg {
        width: 16px;
        height: 16px;
    }

    @media (max-width: 480px) {
        width: 32px;
        height: 32px;
        & > svg { width: 14px; height: 14px; }
    }
`;

const Content = styled.div`
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    overflow: hidden;
`;

const Title = styled.p`
    margin: 0 0 2px;
    font-size: 10px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
`;

const Value = styled.div`
    width: 100%;
    height: 1.5rem;
    font-size: 14px;
    font-weight: 700;
    color: #f1f5f9;
    line-height: 1.2;
    font-family: 'IBM Plex Mono', 'JetBrains Mono', monospace;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    @media (max-width: 480px) {
        font-size: 13px;
        height: 1.3rem;
    }
`;

export default ({ title, copyOnClick, icon, color, className, children }: StatBlockProps) => {
    const { fontSize, ref } = useFitText({ minFontSize: 8, maxFontSize: 500 });

    return (
        <CopyOnClick text={copyOnClick}>
            <Card className={className} $accent={color}>
                <IconBox $accent={color}>
                    <Icon icon={icon} />
                </IconBox>
                <Content>
                    <Title>{title}</Title>
                    <div
                        ref={ref}
                        style={{ fontSize }}
                    >
                        <Value>{children}</Value>
                    </div>
                </Content>
            </Card>
        </CopyOnClick>
    );
};
