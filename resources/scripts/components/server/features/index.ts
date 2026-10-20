import { ComponentType } from 'react';
import EulaModalFeature from '@feature/eula/EulaModalFeature';
import JavaVersionModalFeature from '@feature/JavaVersionModalFeature';
import GSLTokenModalFeature from '@feature/GSLTokenModalFeature';
import PIDLimitModalFeature from '@feature/PIDLimitModalFeature';
import SteamDiskSpaceFeature from '@feature/SteamDiskSpaceFeature';
import HytaleOauthRequireFeature from '@feature/HytaleOauthRequireFeature';

/**
 * Cloud Server: previously these were lazy-loaded, but the
 * react-hot-loader + lazy chunk wiring crashes on minified
 * production bundles (the same root cause as the dashboard
 * black-screen bug). Eager-importing them keeps the server
 * feature modals (EULA / Java / GSL / PID / Steam / Hytale)
 * working reliably when an egg triggers them.
 *
 * Custom features should be registered here so that they are
 * available to be rendered when an egg needs them.
 */
const features: Record<string, ComponentType> = {
    eula: EulaModalFeature,
    java_version: JavaVersionModalFeature,
    gsl_token: GSLTokenModalFeature,
    pid_limit: PIDLimitModalFeature,
    steam_disk_space: SteamDiskSpaceFeature,
    hytale_oauth: HytaleOauthRequireFeature,
};

export default features;
