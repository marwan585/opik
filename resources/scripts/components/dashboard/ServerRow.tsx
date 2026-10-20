import React, { memo, useEffect, useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEthernet, faHdd, faMemory, faMicrochip, faServer } from '@fortawesome/free-solid-svg-icons';
import { Link } from 'react-router-dom';
import { Server } from '@/api/server/getServer';
import getServerResourceUsage, { ServerPowerState, ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import isEqual from 'react-fast-compare';

const isAlarmState = (current: number, limit: number): boolean => limit > 0 && current / (limit * 1024 * 1024) >= 0.9;

type StatAccent = 'green' | 'amber' | 'red' | 'gray';

const accentColor = (accent: StatAccent): string => {
    const colors = {
        green: '#10b981',
        amber: '#f59e0b',
        red: '#ef4444',
        gray: '#64748b',
    };
    return colors[accent];
};

const accentBg = (accent: StatAccent): string => {
    const bg = {
        green: 'rgba(16, 185, 129, 0.12)',
        amber: 'rgba(245, 158, 11, 0.12)',
        red: 'rgba(239, 68, 68, 0.12)',
        gray: 'rgba(100, 116, 139, 0.12)',
    };
    return bg[accent];
};

const cardHoverScale = `
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    &:hover {
        transform: translateY(-2px);
        border-color: rgba(129, 140, 248, 0.35);
        box-shadow: 0 12px 32px -12px rgba(99, 102, 241, 0.35);
    }
`;

const Card = styled.div<{ $status: ServerPowerState | undefined }>`
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: 12fr;
    gap: 16px;
    padding: 18px 20px 18px 22px;
    border-radius: 14px;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgba(255, 255, 255, 0.06);
    box-shadow: 0 4px 16px -8px rgba(0, 0, 0, 0.4);
    ${cardHoverScale}

    @media (min-width: 1024px) {
        grid-template-columns: 6fr 2fr 4fr;
        align-items: center;
    }

    &::before {
        content: '';
        position: absolute;
        left: 0;
        top: 12px;
        bottom: 12px;
        width: 3px;
        border-radius: 0 4px 4px 0;
        background: ${({ $status }) => {
            if (!$status || $status === 'offline') return 'linear-gradient(180deg, #ef4444, #b91c1c)';
            if ($status === 'running') return 'linear-gradient(180deg, #10b981, #047857)';
            return 'linear-gradient(180deg, #f59e0b, #b45309)';
        }};
        box-shadow: 0 0 12px ${({ $status }) => {
            if (!$status || $status === 'offline') return 'rgba(239, 68, 68, 0.5)';
            if ($status === 'running') return 'rgba(16, 185, 129, 0.5)';
            return 'rgba(245, 158, 11, 0.5)';
        }};
    }
`;

const ServerIdentity = styled.div`
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
`;

const ServerIconBox = styled.div<{ $status: ServerPowerState | undefined }>`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    flex-shrink: 0;
    font-size: 18px;
    color: ${({ $status }) => {
        if (!$status || $status === 'offline') return '#f87171';
        if ($status === 'running') return '#34d399';
        return '#fbbf24';
    }};
    background: ${({ $status }) => {
        if (!$status || $status === 'offline') return 'rgba(239, 68, 68, 0.12)';
        if ($status === 'running') return 'rgba(16, 185, 129, 0.12)';
        return 'rgba(245, 158, 11, 0.12)';
    }};
    border: 1px solid rgba(255, 255, 255, 0.05);
`;

const ServerInfo = styled.div`
    min-width: 0;
    flex: 1;
`;

const ServerName = styled.p`
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #f1f5f9;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.01em;
`;

const ServerDesc = styled.p`
    margin: 4px 0 0;
    font-size: 12px;
    color: #64748b;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
`;

const AllocationBox = styled.div`
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 10px;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.06);
    font-size: 12px;
    color: #94a3b8;
    font-family: 'IBM Plex Mono', 'JetBrains Mono', monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;

    svg {
        color: #64748b;
        flex-shrink: 0;
    }
`;

const StatsRow = styled.div`
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
`;

const StatBlock = styled.div<{ $alarm: boolean }>`
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.04);
    min-width: 0;

    ${(p) => p.$alarm && `
        background: rgba(239, 68, 68, 0.08);
        border-color: rgba(239, 68, 68, 0.25);
    `}
`;

const StatHeader = styled.div`
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    font-size: 10px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 600;
`;

const StatValue = styled.div<{ $alarm: boolean }>`
    font-size: 13px;
    font-weight: 700;
    color: ${(p) => (p.$alarm ? '#fca5a5' : '#e2e8f0')};
    line-height: 1.2;
    font-family: 'IBM Plex Mono', 'JetBrains Mono', monospace;
`;

const StatLimit = styled.div`
    font-size: 10px;
    color: #475569;
    margin-top: 2px;
`;

const ProgressBar = styled.div<{ $pct: number; $accent: StatAccent }>`
    margin-top: 6px;
    height: 3px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.05);
    overflow: hidden;

    &::after {
        content: '';
        display: block;
        height: 100%;
        width: ${(p) => Math.min(100, Math.max(0, p.$pct))}%;
        background: ${(p) => accentColor(p.$accent)};
        transition: width 0.4s ease;
        box-shadow: 0 0 6px ${(p) => accentColor(p.$accent)};
    }
`;

const StatusBadge = styled.span<{ $accent: StatAccent }>`
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    color: ${(p) => accentColor(p.$accent)};
    background: ${(p) => accentBg(p.$accent)};
    border: 1px solid ${(p) => `${accentColor(p.$accent)}33`};
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;

    &::before {
        content: '';
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: ${(p) => accentColor(p.$accent)};
        box-shadow: 0 0 6px ${(p) => accentColor(p.$accent)};
    }
`;

type Timer = ReturnType<typeof setInterval>;

export default ({ server, className }: { server: Server; className?: string }) => {
    const interval = useRef<Timer>(null) as React.MutableRefObject<Timer>;
    const [isSuspended, setIsSuspended] = useState(server.status === 'suspended');
    const [stats, setStats] = useState<ServerStats | null>(null);

    const getStats = () =>
        getServerResourceUsage(server.uuid)
            .then((data) => setStats(data))
            .catch((error) => console.error(error));

    useEffect(() => {
        setIsSuspended(stats?.isSuspended || server.status === 'suspended');
    }, [stats?.isSuspended, server.status]);

    useEffect(() => {
        if (isSuspended || server.isNodeUnderMaintenance) return;
        getStats().then(() => {
            interval.current = setInterval(() => getStats(), 30000);
        });
        return () => {
            interval.current && clearInterval(interval.current);
        };
    }, [isSuspended, server.isNodeUnderMaintenance]);

    const alarms = { cpu: false, memory: false, disk: false };
    const pcts = { cpu: 0, memory: 0, disk: 0 };
    if (stats) {
        alarms.cpu = server.limits.cpu === 0 ? false : stats.cpuUsagePercent >= server.limits.cpu * 0.9;
        pcts.cpu = server.limits.cpu === 0 ? Math.min(100, stats.cpuUsagePercent) : Math.min(100, (stats.cpuUsagePercent / server.limits.cpu) * 100);
        alarms.memory = isAlarmState(stats.memoryUsageInBytes, server.limits.memory);
        pcts.memory = server.limits.memory === 0 ? 0 : Math.min(100, (stats.memoryUsageInBytes / mbToBytes(server.limits.memory)) * 100);
        alarms.disk = server.limits.disk === 0 ? false : isAlarmState(stats.diskUsageInBytes, server.limits.disk);
        pcts.disk = server.limits.disk === 0 ? 0 : Math.min(100, (stats.diskUsageInBytes / mbToBytes(server.limits.disk)) * 100);
    }

    const diskLimit = server.limits.disk !== 0 ? bytesToString(mbToBytes(server.limits.disk)) : '∞';
    const memoryLimit = server.limits.memory !== 0 ? bytesToString(mbToBytes(server.limits.memory)) : '∞';
    const cpuLimit = server.limits.cpu !== 0 ? `${server.limits.cpu}%` : '∞';

    const accentFor = (alarm: boolean): StatAccent => (alarm ? 'red' : 'green');

    return (
        <Card as={Link} to={`/server/${server.id}`} className={className} $status={stats?.status}>
            {/* Col 1: Identity */}
            <ServerIdentity>
                <ServerIconBox $status={stats?.status}>
                    <FontAwesomeIcon icon={faServer} />
                </ServerIconBox>
                <ServerInfo>
                    <ServerName>{server.name}</ServerName>
                    {!!server.description && <ServerDesc>{server.description}</ServerDesc>}
                    <div style={{ marginTop: 8 }}>
                        {!stats || isSuspended || server.isNodeUnderMaintenance ? (
                            isSuspended ? (
                                <StatusBadge $accent={'red'}>{server.status === 'suspended' ? 'Suspended' : 'Error'}</StatusBadge>
                            ) : server.isNodeUnderMaintenance ? (
                                <StatusBadge $accent={'amber'}>Under Maintenance</StatusBadge>
                            ) : server.isTransferring || server.status ? (
                                <StatusBadge $accent={'gray'}>
                                    {server.isTransferring
                                        ? 'Transferring'
                                        : server.status === 'installing'
                                        ? 'Installing'
                                        : server.status === 'restoring_backup'
                                        ? 'Restoring'
                                        : 'Unavailable'}
                                </StatusBadge>
                            ) : (
                                <span style={{ fontSize: 11, color: '#64748b' }}>Loading…</span>
                            )
                        ) : (
                            <StatusBadge $accent={stats?.status === 'running' ? 'green' : 'amber'}>
                                {stats?.status === 'running' ? 'Running' : stats?.status || 'Offline'}
                            </StatusBadge>
                        )}
                    </div>
                </ServerInfo>
            </ServerIdentity>

            {/* Col 2: Allocation */}
            <AllocationBox>
                <FontAwesomeIcon icon={faEthernet} />
                <span>
                    {server.allocations
                        .filter((alloc) => alloc.isDefault)
                        .map((allocation) => (
                            <React.Fragment key={allocation.ip + allocation.port.toString()}>
                                {allocation.alias || ip(allocation.ip)}:{allocation.port}
                            </React.Fragment>
                        ))}
                </span>
            </AllocationBox>

            {/* Col 3: Stats with mini progress bars */}
            <StatsRow>
                <StatBlock $alarm={alarms.cpu}>
                    <StatHeader>
                        <FontAwesomeIcon icon={faMicrochip} style={{ fontSize: 9 }} />
                        CPU
                    </StatHeader>
                    <StatValue $alarm={alarms.cpu}>
                        {stats ? `${stats.cpuUsagePercent.toFixed(1)}%` : '—'}
                    </StatValue>
                    <StatLimit>of {cpuLimit}</StatLimit>
                    {stats && <ProgressBar $pct={pcts.cpu} $accent={accentFor(alarms.cpu)} />}
                </StatBlock>

                <StatBlock $alarm={alarms.memory}>
                    <StatHeader>
                        <FontAwesomeIcon icon={faMemory} style={{ fontSize: 9 }} />
                        RAM
                    </StatHeader>
                    <StatValue $alarm={alarms.memory}>
                        {stats ? bytesToString(stats.memoryUsageInBytes) : '—'}
                    </StatValue>
                    <StatLimit>of {memoryLimit}</StatLimit>
                    {stats && <ProgressBar $pct={pcts.memory} $accent={accentFor(alarms.memory)} />}
                </StatBlock>

                <StatBlock $alarm={alarms.disk}>
                    <StatHeader>
                        <FontAwesomeIcon icon={faHdd} style={{ fontSize: 9 }} />
                        Disk
                    </StatHeader>
                    <StatValue $alarm={alarms.disk}>
                        {stats ? bytesToString(stats.diskUsageInBytes) : '—'}
                    </StatValue>
                    <StatLimit>of {diskLimit}</StatLimit>
                    {stats && <ProgressBar $pct={pcts.disk} $accent={accentFor(alarms.disk)} />}
                </StatBlock>
            </StatsRow>
        </Card>
    );
};
