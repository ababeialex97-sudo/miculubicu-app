import { useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { ScrollView, StyleSheet } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { queryKeys, useUpdateProfile } from '@/api/hooks';
import type { Customer } from '@/api/types';
import { AppText, Button, Centered, Field } from '@/components/ui';
import { colors, fonts } from '@/constants/theme';
import { unregisterForPush } from '@/lib/push';
import { useSession } from '@/store/session';

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

  const logout = async () => {
    await unregisterForPush();
    signOut();
    queryClient.removeQueries({ queryKey: queryKeys.orders });
  };

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
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 20, gap: 14 },
  error: { fontFamily: fonts.medium, fontSize: 13, color: '#FF8A75' },
});
