import React, { useState } from 'react';
import { Link, RouteComponentProps } from 'react-router-dom';
import loginCheckpoint from '@/api/auth/loginCheckpoint';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import PostLoginLoadingOverlay from '@/components/auth/PostLoginLoadingOverlay';
import { ActionCreator } from 'easy-peasy';
import { StaticContext } from 'react-router';
import { useFormikContext, withFormik } from 'formik';
import useFlash from '@/plugins/useFlash';
import { FlashStore } from '@/state/flashes';
import Field from '@/components/elements/Field';
import tw from 'twin.macro';
import styled from 'styled-components/macro';

interface Values {
    code: string;
    recoveryCode: '';
}

type OwnProps = RouteComponentProps<Record<string, string | undefined>, StaticContext, { token?: string }>;

type Props = OwnProps & {
    clearAndAddHttpError: ActionCreator<FlashStore['clearAndAddHttpError']['payload']>;
};

const InputGroup = styled.div`
    margin-bottom: 18px;

    label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 7px;
    }

    input {
        width: 100%;
        padding: 12px 14px;
        font-size: 14px;
        color: #f1f5f9;
        background: rgba(15, 23, 42, 0.5);
        border: 1px solid rgba(100, 116, 139, 0.25);
        border-radius: 10px;
        outline: none;
        transition: all 0.18s ease;
        font-family: inherit;
        text-align: center;
        letter-spacing: 0.15em;
        font-size: 16px;

        &::placeholder { color: #64748b; letter-spacing: 0; font-size: 14px; }
        &:hover:not(:disabled) { border-color: rgba(129, 140, 248, 0.35); background: rgba(15, 23, 42, 0.7); }
        &:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15); background: rgba(15, 23, 42, 0.85); }
        &:disabled { opacity: 0.6; cursor: not-allowed; }
    }

    .input-help.error { margin-top: 7px; font-size: 11px; color: #fca5a5; font-weight: 500; }
    .input-help { margin-top: 7px; font-size: 11px; color: #64748b; }
`;

const SubmitButton = styled.button`
    width: 100%;
    padding: 13px 20px;
    margin-top: 4px;
    font-size: 14px;
    font-weight: 600;
    color: #ffffff;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 8px 20px -8px rgba(79, 70, 229, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.15);
    letter-spacing: 0.02em;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    &:hover:not(:disabled) {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        box-shadow: 0 12px 28px -8px rgba(79, 70, 229, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.2);
        transform: translateY(-1px);
    }
    &:active:not(:disabled) { transform: translateY(0); }
    &:disabled { opacity: 0.75; cursor: not-allowed; }
`;

const SpinnerWrap = styled.div`
    display: inline-block;
    width: 15px;
    height: 15px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    @keyframes spin { to { transform: rotate(360deg); } }
`;

const SecondaryAction = styled.div`
    text-align: center;
    margin-top: 18px;

    span {
        font-size: 12px;
        color: #818cf8;
        text-decoration: none;
        font-weight: 500;
        cursor: pointer;
        transition: color 0.15s ease;
    }
    span:hover { color: #a5b4fc; text-decoration: underline; }
`;

const BottomRow = styled.div`
    display: flex;
    justify-content: center;
    margin-top: 14px;

    a {
        font-size: 12px;
        color: #94a3b8;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.15s ease;
    }
    a:hover { color: #c7d2fe; text-decoration: underline; }
`;

const LoginCheckpointContainerWithOverlay: React.FC<any> = (props) => {
    const { isSubmitting, setFieldValue } = useFormikContext<Values>();
    const [isMissingDevice, setIsMissingDevice] = useState(false);
    const [showOverlay, setShowOverlay] = useState(false);
    const [intendedUrl, setIntendedUrl] = useState<string | undefined>(undefined);
    // expose setters to outer handleSubmit via window-scoped handler
    (window as any).__cloudServerPostLogin = { setShowOverlay, setIntendedUrl };

    return (
        <>
            {showOverlay && (
                <PostLoginLoadingOverlay
                    duration={2000}
                    onComplete={() => {
                        // @ts-expect-error window.location is the simplest cross-bundle way to redirect
                        window.location = intendedUrl || '/';
                    }}
                />
            )}
            <LoginFormContainer title={isMissingDevice ? 'Kode Recovery' : 'Verifikasi 2FA'} css={tw`w-full flex`}>
                <InputGroup>
                    <Field
                        name={isMissingDevice ? 'recoveryCode' : 'code'}
                        title={isMissingDevice ? 'Recovery Code' : 'Authentication Code'}
                        description={
                            isMissingDevice
                                ? 'Masukkan salah satu kode recovery yang Anda dapat saat setup 2FA.'
                                : 'Masukkan kode 2-faktor dari aplikasi authenticator Anda.'
                        }
                        type={'text'}
                        autoComplete={'one-time-code'}
                        autoFocus
                        placeholder={isMissingDevice ? 'xxxx-xxxx' : '000000'}
                    />
                </InputGroup>
                <div css={tw`mt-2`}>
                    <SubmitButton type={'submit'} disabled={isSubmitting}>
                        {isSubmitting ? (
                            <>
                                <SpinnerWrap />
                                <span>Memverifikasi...</span>
                            </>
                        ) : (
                            <span>Lanjutkan</span>
                        )}
                    </SubmitButton>
                </div>
                <SecondaryAction>
                    <span
                        onClick={() => {
                            setFieldValue('code', '');
                            setFieldValue('recoveryCode', '');
                            setIsMissingDevice((s) => !s);
                        }}
                    >
                        {!isMissingDevice ? '← Saya kehilangan perangkat' : '← Saya punya perangkat'}
                    </span>
                </SecondaryAction>
                <BottomRow>
                    <Link to={'/auth/login'}>Kembali ke Login</Link>
                </BottomRow>
            </LoginFormContainer>
        </>
    );
};

const EnhancedForm = withFormik<Props, Values>({
    handleSubmit: ({ code, recoveryCode }, { setSubmitting, props: { clearAndAddHttpError, location } }) => {
        loginCheckpoint(location.state?.token || '', code, recoveryCode)
            .then((response) => {
                if (response.complete) {
                    const overlay = (window as any).__cloudServerPostLogin;
                    if (overlay) {
                        overlay.setIntendedUrl(response.intended || '/');
                        overlay.setShowOverlay(true);
                    } else {
                        // @ts-expect-error this is valid
                        window.location = response.intended || '/';
                    }
                    return;
                }
                setSubmitting(false);
            })
            .catch((error) => {
                console.error(error);
                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    },

    mapPropsToValues: () => ({
        code: '',
        recoveryCode: '',
    }),
})(LoginCheckpointContainerWithOverlay);

export default ({ history, location, ...props }: OwnProps) => {
    const { clearAndAddHttpError } = useFlash();

    if (!location.state?.token) {
        history.replace('/auth/login');
        return null;
    }

    return (
        <EnhancedForm clearAndAddHttpError={clearAndAddHttpError} history={history} location={location} {...props} />
    );
};
