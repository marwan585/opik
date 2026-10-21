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
 * Cloud Server — Professional Login Form (v2)
 *
 * - Clean input fields with floating-style labels (uppercase mini labels).
 * - Indigo accent on focus.
 * - Solid gradient submit button with subtle shimmer.
 * - Inline "Lupa password?" link right-aligned under password field.
 * - Recaptcha support kept intact.
 */

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

        &::placeholder {
            color: #64748b;
        }

        &:hover:not(:disabled) {
            border-color: rgba(129, 140, 248, 0.35);
            background: rgba(15, 23, 42, 0.7);
        }

        &:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
            background: rgba(15, 23, 42, 0.85);
        }

        &:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    }

    .input-help.error {
        margin-top: 7px;
        font-size: 11px;
        color: #fca5a5;
        font-weight: 500;
    }
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
    box-shadow:
        0 8px 20px -8px rgba(79, 70, 229, 0.6),
        inset 0 1px 0 rgba(255, 255, 255, 0.15);
    letter-spacing: 0.02em;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    &:hover:not(:disabled) {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        box-shadow:
            0 12px 28px -8px rgba(79, 70, 229, 0.7),
            inset 0 1px 0 rgba(255, 255, 255, 0.2);
        transform: translateY(-1px);
    }

    &:active:not(:disabled) {
        transform: translateY(0);
        box-shadow:
            0 4px 12px -4px rgba(79, 70, 229, 0.5),
            inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }

    &:disabled {
        opacity: 0.75;
        cursor: not-allowed;
    }
`;

const SpinnerWrap = styled.div`
    display: inline-block;
    width: 15px;
    height: 15px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
`;

const BottomRow = styled.div`
    display: flex;
    justify-content: flex-end;
    margin-top: 14px;

    a {
        font-size: 12px;
        color: #818cf8;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.15s ease;
        letter-spacing: 0.01em;
    }

    a:hover {
        color: #a5b4fc;
        text-decoration: underline;
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
                    <LoginFormContainer title={'Masuk ke akun Anda'} css={tw`w-full flex`}>
                        <InputGroup>
                            <Field
                                type={'text'}
                                label={'Username atau Email'}
                                name={'username'}
                                disabled={isSubmitting}
                                placeholder={'masukkan username atau email'}
                            />
                        </InputGroup>
                        <InputGroup>
                            <Field
                                type={'password'}
                                label={'Password'}
                                name={'password'}
                                disabled={isSubmitting}
                                placeholder={'masukkan password'}
                            />
                        </InputGroup>
                        <BottomRow>
                            <Link to={'/auth/password'}>Lupa password?</Link>
                        </BottomRow>
                        <div css={tw`mt-5`}>
                            <SubmitButton type={'submit'} disabled={isSubmitting}>
                                {isSubmitting ? (
                                    <>
                                        <SpinnerWrap />
                                        <span>Memproses...</span>
                                    </>
                                ) : (
                                    <span>Masuk</span>
                                )}
                            </SubmitButton>
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
                    </LoginFormContainer>
                )}
            </Formik>
        </>
    );
};

export default LoginContainer;
