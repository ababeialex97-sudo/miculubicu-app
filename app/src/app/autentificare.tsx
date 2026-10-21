import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Svg, { Path } from 'react-native-svg';

import { useConfig, useLogin, usePasswordReset, useRegister, useSocialLogin, type SocialLogin } from '@/api/hooks';
import { AppText, Button, Field, Segmented } from '@/components/ui';
import { colors, fonts, minTouchSize } from '@/constants/theme';
import { SignInCancelled, appleAvailable, googleAvailable, loadApple, signInWithApple, signInWithGoogle } from '@/lib/social-login';

type Mode = 'login' | 'register';

export default function AuthScreen() {
  const [mode, setMode] = useState<Mode>('login');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [phone, setPhone] = useState('');

  const login = useLogin();
  const register = useRegister();
  const reset = usePasswordReset();
  const mutation = mode === 'login' ? login : register;

  // Whatever screen opened the modal (cart, account) is still underneath.
  const done = () => router.back();

  const config = useConfig();
  const social = useSocialLogin();
  const [socialError, setSocialError] = useState<string | null>(null);
  const [appleReady, setAppleReady] = useState(false);
  useEffect(() => {
    void appleAvailable().then(setAppleReady);
  }, []);
  const showGoogle = config.data?.social_login?.google === true && googleAvailable();
  const showApple = config.data?.social_login?.apple === true && appleReady;
  const Apple = showApple ? loadApple() : null;

  const continueWith = async (getCredential: () => Promise<SocialLogin>) => {
    setSocialError(null);
    try {
      const credential = await getCredential();
      social.mutate(credential, { onSuccess: done, onError: (error) => setSocialError(error.message) });
    } catch (error) {
      if (!(error instanceof SignInCancelled)) {
        setSocialError(error instanceof Error ? error.message : 'Conectarea nu a reușit. Încearcă din nou.');
      }
    }
  };

  const submit = () => {
    if (mode === 'login') {
      login.mutate({ email: email.trim(), password }, { onSuccess: done });
    } else {
      register.mutate({ email: email.trim(), password, first_name: firstName.trim(), last_name: lastName.trim(), phone: phone.trim() }, { onSuccess: done });
    }
  };

  return (
    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.screen}>
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <AppText variant="title">{mode === 'login' ? 'Intră în cont' : 'Cont nou'}</AppText>
        <AppText>Contul e același ca pe miculubicu.ro.</AppText>

        {showApple || showGoogle ? (
          <View style={{ gap: 10 }}>
            {Apple ? (
              <Apple.AppleAuthenticationButton
                buttonType={Apple.AppleAuthenticationButtonType.CONTINUE}
                buttonStyle={Apple.AppleAuthenticationButtonStyle.WHITE}
                cornerRadius={12}
                style={styles.socialButton}
                onPress={() => void continueWith(signInWithApple)}
              />
            ) : null}
            {showGoogle ? (
              <Pressable accessibilityRole="button" onPress={() => void continueWith(signInWithGoogle)} disabled={social.isPending} style={[styles.socialButton, styles.googleButton]}>
                <GoogleLogo />
                <AppText style={styles.googleLabel}>Continuă cu Google</AppText>
              </Pressable>
            ) : null}
            {social.isPending ? <ActivityIndicator color={colors.accent} /> : null}
            {socialError ? <AppText style={styles.error}>{socialError}</AppText> : null}
            <View style={styles.dividerRow}>
              <View style={styles.divider} />
              <AppText variant="muted">sau cu email</AppText>
              <View style={styles.divider} />
            </View>
          </View>
        ) : null}

        <Segmented
          value={mode}
          onChange={setMode}
          options={[
            { value: 'login', label: 'Am cont' },
            { value: 'register', label: 'Cont nou' },
          ]}
        />

        {mode === 'register' ? (
          <>
            <Field label="Prenume" value={firstName} onChangeText={setFirstName} autoComplete="given-name" />
            <Field label="Nume" value={lastName} onChangeText={setLastName} autoComplete="family-name" />
            <Field label="Telefon" value={phone} onChangeText={setPhone} keyboardType="phone-pad" autoComplete="tel" />
          </>
        ) : null}

        <Field label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" autoComplete="email" />
        <Field label="Parolă" value={password} onChangeText={setPassword} secureTextEntry autoComplete={mode === 'login' ? 'current-password' : 'new-password'} />
        {mode === 'register' ? <AppText variant="muted">Cel puțin 8 caractere.</AppText> : null}

        {mutation.isError ? <AppText style={styles.error}>{mutation.error.message}</AppText> : null}

        <Button label={mode === 'login' ? 'Intră în cont' : 'Creează contul'} loading={mutation.isPending} onPress={submit} />

        {mode === 'login' ? (
          <Button
            label={reset.isSuccess ? 'Verifică emailul' : 'Am uitat parola'}
            variant="outline"
            disabled={!email.trim() || reset.isSuccess}
            loading={reset.isPending}
            onPress={() => reset.mutate(email.trim())}
          />
        ) : null}
        {reset.isSuccess ? <AppText>{reset.data.message}</AppText> : null}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

// Google's "G" mark, as its sign-in branding guidelines require on the button.
function GoogleLogo() {
  return (
    <Svg width={20} height={20} viewBox="0 0 48 48" accessible={false}>
      <Path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
      <Path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
      <Path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
      <Path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
    </Svg>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 20, gap: 14 },
  error: { fontFamily: fonts.medium, fontSize: 13, color: colors.error },
  socialButton: { height: 48, minHeight: minTouchSize, width: '100%' },
  // Google's light button: white with dark text, which also reads well on the charcoal background.
  googleButton: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, borderRadius: 12, backgroundColor: '#FFFFFF' },
  googleLabel: { fontFamily: fonts.semibold, fontSize: 16, color: '#1F1F1F' },
  dividerRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  divider: { flex: 1, height: 1, backgroundColor: colors.border },
});
