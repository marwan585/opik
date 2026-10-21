import React, { memo } from 'react';
import { ServerContext } from '@/state/server';
import Can from '@/components/elements/Can';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import isEqual from 'react-fast-compare';
import Spinner from '@/components/elements/Spinner';
import Features from '@feature/Features';
import Console from '@/components/server/console/Console';
import StatGraphs from '@/components/server/console/StatGraphs';
import PowerButtons from '@/components/server/console/PowerButtons';
import ServerDetailsBlock from '@/components/server/console/ServerDetailsBlock';
import { Alert } from '@/components/elements/alert';
import styled, { keyframes } from 'styled-components/macro';

export type PowerAction = 'start' | 'stop' | 'restart' | 'kill';

const fadeInUp = keyframes`
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
`;

const ConsoleLayout = styled.div`
    animation: ${fadeInUp} 0.35s ease-out;
`;

const HeaderRow = styled.div`
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 20px;

    @media (min-width: 768px) {
        flex-direction: row;
        align-items: flex-end;
        justify-content: space-between;
    }
`;

const ServerTitleBlock = styled.div`
    flex: 1;
    min-width: 0;
`;

const ServerName = styled.h1`
    margin: 0 0 6px;
    font-size: clamp(20px, 4vw, 28px);
    font-weight: 700;
    color: #f1f5f9;
    line-height: 1.2;
    letter-spacing: -0.02em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
`;

const ServerDescription = styled.p`
    margin: 0;
    font-size: 13px;
    color: #94a3b8;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
`;

const PowerButtonContainer = styled.div`
    display: flex;
    gap: 8px;
    flex-wrap: wrap;

    @media (min-width: 768px) {
        justify-content: flex-end;
    }

    & > button {
        flex: 1;
        min-width: 90px;
    }
`;

const ConsoleCard = styled.div`
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.6) 0%, rgba(15, 23, 42, 0.8) 100%);
    border: 1px solid rgba(129, 140, 248, 0.12);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 6px 20px -10px rgba(0, 0, 0, 0.5);
    margin-bottom: 16px;
`;

const ConsoleHeader = styled.div`
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    background: rgba(15, 23, 42, 0.6);
    border-bottom: 1px solid rgba(129, 140, 248, 0.1);
`;

const ConsoleHeaderLabel = styled.div`
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.08em;

    &::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #34d399;
        box-shadow: 0 0 6px #34d399;
    }
`;

const ConsoleBody = styled.div`
    padding: 0;

    & > div:first-child {
        margin: 0 !important;
        width: 100% !important;
    }
`;

const StatsGrid = styled.div`
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 16px;

    @media (min-width: 640px) {
        grid-template-columns: repeat(3, 1fr);
    }

    @media (min-width: 1024px) {
        grid-template-columns: repeat(6, 1fr);
    }

    @media (max-width: 480px) {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
`;

const ChartsWrapper = styled.div`
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.5) 0%, rgba(15, 23, 42, 0.7) 100%);
    border: 1px solid rgba(129, 140, 248, 0.1);
    border-radius: 14px;
    padding: 16px;
    box-shadow: 0 6px 20px -10px rgba(0, 0, 0, 0.4);
`;

const ServerConsoleContainer = () => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const description = ServerContext.useStoreState((state) => state.server.data!.description);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const isNodeUnderMaintenance = ServerContext.useStoreState((state) => state.server.data!.isNodeUnderMaintenance);

    return (
        <ServerContentBlock title={'Console'}>
            <ConsoleLayout>
                {(isNodeUnderMaintenance || isInstalling || isTransferring) && (
                    <Alert type={'warning'} className={'mb-4'}>
                        {isNodeUnderMaintenance
                            ? 'The node of this server is currently under maintenance and all actions are unavailable.'
                            : isInstalling
                            ? 'This server is currently running its installation process and most actions are unavailable.'
                            : 'This server is currently being transferred to another node and all actions are unavailable.'}
                    </Alert>
                )}

                {/* ===== Header: Server name + Power buttons ===== */}
                <HeaderRow>
                    <ServerTitleBlock>
                        <ServerName title={name}>{name}</ServerName>
                        {description && <ServerDescription>{description}</ServerDescription>}
                    </ServerTitleBlock>
                    <Can action={['control.start', 'control.stop', 'control.restart']} matchAny>
                        <PowerButtonContainer>
                            <PowerButtons />
                        </PowerButtonContainer>
                    </Can>
                </HeaderRow>

                {/* ===== Console card with header label ===== */}
                <ConsoleCard>
                    <ConsoleHeader>
                        <ConsoleHeaderLabel>Console Output</ConsoleHeaderLabel>
                    </ConsoleHeader>
                    <ConsoleBody>
                        <Spinner.Suspense>
                            <Console />
                        </Spinner.Suspense>
                    </ConsoleBody>
                </ConsoleCard>

                {/* ===== Stats grid ===== */}
                <StatsGrid>
                    <ServerDetailsBlock />
                </StatsGrid>

                {/* ===== Charts ===== */}
                <ChartsWrapper>
                    <Spinner.Suspense>
                        <StatGraphs />
                    </Spinner.Suspense>
                </ChartsWrapper>

                <Features enabled={eggFeatures} />
            </ConsoleLayout>
        </ServerContentBlock>
    );
};

export default memo(ServerConsoleContainer, isEqual);
