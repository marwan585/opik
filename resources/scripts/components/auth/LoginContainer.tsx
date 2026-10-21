import React, { useEffect, useRef, useState } from 'react';
import { Link, RouteComponentProps } from 'react-router-dom';
import login from '@/api/auth/login';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import PostLoginLoadingOverlay from '@/components/auth/PostLoginLoadingOverlay';
import { useStoreState } from 'easy-peasy';
import { Formik, FormikHelpers } from 'formik';
import { object, string } from 'yup';
import Field from '@/components/elements/Field';
import tw from 'twin.macro';
import Reaptcha from 'reaptcha';
import useFlash from '@/plugins/useFlash';
import styled from 'styled-components/macro';

interface Values {
    username: string;
    password: string;
}

/**
 * Cloud Server — Modern Login Page
 *
 * Uses LoginFormContainer (which provides the glassmorphism card with
 * animated aurora background) and adds modern input styling + a
 * gradient login button that matches the premium look.
 *
 * Note: Field is rendered WITHOUT the `light` prop so the input uses
 * the dark theme (bg-neutral-600 border-neutral-500 text-neutral-200)
 * which matches the dark glassmorphism card. The styled InputOverride
 * below tweaks the input border + radius to match the modern look.
 */

const InputOverride = styled.div`
    label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 6px;
    }

    input {
        background: rgba(15, 23, 42, 0.6) !important;
        border: 1px solid rgba(129, 140, 248, 0.22) !important;
        border-radius: 10px !important;
        color: #f1f5f9 !important;
        padding: 12px 14px !important;
        font-size: 14px !important;
        transition: all 0.15s ease !important;

        &::placeholder {
            color: #64748b;
        }

        &:hover {
            border-color: rgba(129, 140, 248, 0.4) !important;
        }

        &:focus {
            border-color: #818cf8 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18) !important;
            background: rgba(15, 23, 42, 0.85) !important;
        }
    }

    .input-help.error {
        margin-top: 6px;
        font-size: 11px;
        color: #fca5a5;
    }
`;

const GradientButton = styled.button<{ $disabled?: boolean }>`
    width: 100%;
    padding: 13px 20px;
    margin-top: 6px;
    font-size: 14px;
    font-weight: 600;
    color: #ffffff;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #06b6d4 100%);
    background-size: 200% 100%;
    background-position: 0% 0%;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 8px 24px -8px rgba(99, 102, 241, 0.55);
    letter-spacing: 0.02em;

    &:hover:not(:disabled) {
        background-position: 100% 0%;
        box-shadow: 0 12px 32px -8px rgba(99, 102, 241, 0.7);
        transform: translateY(-1px);
    }

    &:active:not(:disabled) {
        transform: translateY(0);
    }

    &:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
`;

const SpinnerWrap = styled.div`
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    vertical-align: middle;
    margin-right: 8px;

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
`;

const ForgotLink = styled.div`
    text-align: center;
    margin-top: 22px;

    a {
        font-size: 12px;
        color: #94a3b8;
        text-decoration: none;
        transition: color 0.15s ease;
        letter-spacing: 0.02em;
    }

    a:hover {
        color: #c7d2fe;
    }
`;

const LoginContainer = ({ history }: RouteComponentProps) => {
    const ref = useRef<Reaptcha>(null);
    const [token, setToken] = useState('');
    const [showPostLoginOverlay, setShowPostLoginOverlay] = useState(false);
    const [intendedUrl, setIntendedUrl] = useState<string | undefined>(undefined);

    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { enabled: recaptchaEnabled, siteKey } = useStoreState((state) => state.settings.data!.recaptcha);

    useEffect(() => {
        clearFlashes();
    }, []);

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();

        if (recaptchaEnabled && !token) {
            ref.current!.execute().catch((error) => {
                console.error(error);
                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
            return;
        }

        login({ ...values, recaptchaData: token })
            .then((response) => {
                if (response.complete) {
                    setIntendedUrl(response.intended || '/');
                    setShowPostLoginOverlay(true);
                    return;
                }
                history.replace('/auth/login/checkpoint', { token: response.confirmationToken });
            })
            .catch((error) => {
                console.error(error);
                setToken('');
                if (ref.current) ref.current.reset();
                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    return (
        <>
            {showPostLoginOverlay && (
                <PostLoginLoadingOverlay
                    duration={2000}
                    onComplete={() => {
                        // @ts-expect-error window.location is the simplest cross-bundle way to redirect
                        window.location = intendedUrl || '/';
                    }}
                />
            )}
            <Formik
                onSubmit={onSubmit}
                initialValues={{ username: '', password: '' }}
                validationSchema={object().shape({
                    username: string().required('Username atau email harus diisi.'),
                    password: string().required('Password harus diisi.'),
                })}
            >
                {({ isSubmitting, setSubmitting, submitForm }) => (
                    <LoginFormContainer title={'Masuk ke Akun Anda'} css={tw`w-full flex`}>
                        <InputOverride>
                            <Field
                                type={'text'}
                                label={'Username atau Email'}
                                name={'username'}
                                disabled={isSubmitting}
                                placeholder={'masukkan username/email Anda'}
                            />
                            <div css={tw`mt-4`}>
                                <Field
                                    type={'password'}
                                    label={'Password'}
                                    name={'password'}
                                    disabled={isSubmitting}
                                    placeholder={'masukkan password Anda'}
                                />
                            </div>
                        </InputOverride>
                        <div css={tw`mt-6`}>
                            <GradientButton type={'submit'} disabled={isSubmitting}>
                                {isSubmitting ? (
                                    <>
                                        <SpinnerWrap />
                                        Memproses...
                                    </>
                                ) : (
                                    '🔐 Masuk'
                                )}
                            </GradientButton>
                        </div>
                        {recaptchaEnabled && (
                            <Reaptcha
                                ref={ref}
                                size={'invisible'}
                                sitekey={siteKey || '_invalid_key'}
                                onVerify={(response) => {
                                    setToken(response);
                                    submitForm();
                                }}
                                onExpire={() => {
                                    setSubmitting(false);
                                    setToken('');
                                }}
                            />
                        )}
                        <ForgotLink>
                            <Link to={'/auth/password'}>Lupa password?</Link>
                        </ForgotLink>
                    </LoginFormContainer>
                )}
            </Formik>
        </>
    );
};

export default LoginContainer;
