import { useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import * as WebBrowser from 'expo-web-browser';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { queryKeys, useDeleteAccount, useUpdateProfile } from '@/api/hooks';
import type { Customer } from '@/api/types';
import { AppText, Button, Centered, Field } from '@/components/ui';
import { colors, fonts, minTouchSize } from '@/constants/theme';
import { unregisterForPush } from '@/lib/push';
import { useCart } from '@/store/cart';
import { useSession } from '@/store/session';

const PRIVACY_URL = process.env.EXPO_PUBLIC_PRIVACY_URL ?? 'https://miculubicu.ro/politica-de-confidentialitate/';

export default function AccountScreen() {
  const customer = useSession((s) => s.customer);

  if (!customer) {
    return (
      <Centered>
        <AppText variant="title">Contul tău</AppText>
        <AppText style={{ textAlign: 'center' }}>Cu un cont comanzi mai repede și îți vezi istoricul.</AppText>
        <Button label="Intră în cont sau creează unul" onPress={() => router.push('/autentificare')} />
      </Centered>
    );
  }

  // Keyed by customer so the form resets after logging in as someone else.
  return <ProfileForm key={customer.id} customer={customer} />;
}

function ProfileForm({ customer }: { customer: Customer }) {
  const queryClient = useQueryClient();
  const signOut = useSession((s) => s.signOut);
  const update = useUpdateProfile();
  const [firstName, setFirstName] = useState(customer.first_name);
  const [lastName, setLastName] = useState(customer.last_name);
  const [phone, setPhone] = useState(customer.phone);
  const [address1, setAddress1] = useState(customer.address.address_1);
  const [address2, setAddress2] = useState(customer.address.address_2);
  const [city, setCity] = useState(customer.address.city);

  const save = () =>
    update.mutate({
      first_name: firstName.trim(),
      last_name: lastName.trim(),
      phone: phone.trim(),
      address: { address_1: address1.trim(), address_2: address2.trim(), city: city.trim() },
    });

  const deleteAccount = useDeleteAccount();
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const [password, setPassword] = useState('');

  const clearAccountData = () => {
    signOut();
    queryClient.removeQueries({ queryKey: queryKeys.orders });
    queryClient.removeQueries({ queryKey: queryKeys.loyalty });
    queryClient.removeQueries({ queryKey: ['cart-preview'] });
    // Reward codes belong to this account.
    for (const code of useCart.getState().couponCodes) {
      useCart.getState().removeCoupon(code);
    }
  };

  const logout = async () => {
    await unregisterForPush();
    clearAccountData();
  };

  // The server drops the account's push tokens together with the account.
  const confirmDelete = () =>
    deleteAccount.mutate(password, {
      onSuccess: () => {
        clearAccountData();
        router.replace('/');
      },
    });

  return (
    <SafeAreaView edges={['top']} style={styles.screen}>
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <AppText variant="title">Contul tău</AppText>
        <AppText variant="muted">{customer.email}</AppText>

        <Field label="Prenume" value={firstName} onChangeText={setFirstName} />
        <Field label="Nume" value={lastName} onChangeText={setLastName} />
        <Field label="Telefon" value={phone} onChangeText={setPhone} keyboardType="phone-pad" />
        <Field label="Adresa" value={address1} onChangeText={setAddress1} />
        <Field label="Bloc, scară, apartament" value={address2} onChangeText={setAddress2} />
        <Field label="Localitatea" value={city} onChangeText={setCity} />

        {update.isError ? <AppText style={styles.error}>{update.error.message}</AppText> : null}
        {update.isSuccess ? <AppText>Datele au fost salvate.</AppText> : null}
        <Button label="Salvează" loading={update.isPending} onPress={save} />
        <Button label="Ieși din cont" variant="outline" onPress={() => void logout()} />

        <Pressable accessibilityRole="link" onPress={() => void WebBrowser.openBrowserAsync(PRIVACY_URL)} style={styles.link}>
          <AppText style={styles.linkText}>Politica de confidențialitate</AppText>
        </Pressable>

        {confirmingDelete ? (
          <View style={styles.danger}>
            <AppText variant="bodyStrong">Ștergi contul?</AppText>
            <AppText variant="muted">Se șterg datele contului, cardul de fidelitate și cupoanele tale. Comenzile deja plasate rămân la restaurant pentru evidența contabilă.</AppText>
            <Field label="Parola, pentru confirmare" value={password} onChangeText={setPassword} secureTextEntry autoComplete="current-password" />
            {deleteAccount.isError ? <AppText style={styles.error}>{deleteAccount.error.message}</AppText> : null}
            <Button label="Șterge definitiv contul" loading={deleteAccount.isPending} disabled={!password} onPress={confirmDelete} />
            <Button label="Renunță" variant="outline" onPress={() => setConfirmingDelete(false)} />
          </View>
        ) : (
          <Pressable accessibilityRole="button" onPress={() => setConfirmingDelete(true)} style={styles.link}>
            <AppText style={styles.dangerText}>Șterge contul</AppText>
          </Pressable>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 20, gap: 14 },
  error: { fontFamily: fonts.medium, fontSize: 13, color: '#FF8A75' },
  link: { minHeight: minTouchSize, justifyContent: 'center', alignSelf: 'flex-start' },
  linkText: { fontFamily: fonts.semibold, fontSize: 14, color: colors.accent },
  dangerText: { fontFamily: fonts.semibold, fontSize: 14, color: '#FF8A75' },
  danger: { gap: 12, padding: 16, borderRadius: 16, borderWidth: 1, borderColor: colors.primary, backgroundColor: colors.surface },
});
