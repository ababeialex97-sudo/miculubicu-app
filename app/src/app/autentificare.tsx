import { router } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet } from 'react-native';

import { useLogin, usePasswordReset, useRegister } from '@/api/hooks';
import { AppText, Button, Field, Segmented } from '@/components/ui';
import { colors, fonts } from '@/constants/theme';

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

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 20, gap: 14 },
  error: { fontFamily: fonts.medium, fontSize: 13, color: '#FF8A75' },
});
