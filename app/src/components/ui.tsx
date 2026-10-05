import type { ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, TextInput, View, type TextInputProps, type TextProps } from 'react-native';

import { colors, fonts, minTouchSize } from '@/constants/theme';

type Variant = 'title' | 'heading' | 'body' | 'bodyStrong' | 'muted' | 'price' | 'small';

export function AppText({ variant = 'body', style, ...props }: TextProps & { variant?: Variant }) {
  return <Text {...props} style={[textStyles[variant], style]} />;
}

type ButtonProps = {
  label: string;
  onPress: () => void;
  variant?: 'primary' | 'outline' | 'accent';
  disabled?: boolean;
  loading?: boolean;
  accessibilityLabel?: string;
  right?: ReactNode;
};

export function Button({ label, onPress, variant = 'primary', disabled, loading, accessibilityLabel, right }: ButtonProps) {
  const inactive = disabled || loading;
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityState={{ disabled: inactive, busy: loading }}
      disabled={inactive}
      onPress={onPress}
      style={({ pressed }) => [
        styles.button,
        variant === 'primary' && styles.buttonPrimary,
        variant === 'outline' && styles.buttonOutline,
        variant === 'accent' && styles.buttonAccent,
        (pressed || inactive) && styles.dimmed,
      ]}>
      {loading ? (
        <ActivityIndicator color={variant === 'outline' ? colors.text : colors.onPrimary} />
      ) : (
        <>
          <Text style={[styles.buttonLabel, variant === 'outline' && { color: colors.text }, variant === 'accent' && { color: colors.onAccent }]}>
            {label}
          </Text>
          {right}
        </>
      )}
    </Pressable>
  );
}

// "filter" chips (menu categories) fill red when selected; "option" chips (preferences) get a yellow outline.
export function Chip({ label, selected, onPress, kind = 'option' }: { label: string; selected: boolean; onPress: () => void; kind?: 'option' | 'filter' }) {
  const selectedStyle = kind === 'filter' ? styles.chipFilterSelected : styles.chipSelected;
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected }}
      onPress={onPress}
      style={[styles.chip, selected ? selectedStyle : styles.chipIdle]}>
      <Text style={[styles.chipLabel, selected && { fontFamily: fonts.semibold }, selected && kind === 'filter' && { color: colors.onPrimary }]}>{label}</Text>
    </Pressable>
  );
}

export function Segmented<T extends string>({ options, value, onChange }: { options: { value: T; label: string; disabled?: boolean }[]; value: T; onChange: (value: T) => void }) {
  return (
    <View style={styles.segmented}>
      {options.map((option) => {
        const selected = option.value === value;
        return (
          <Pressable
            key={option.value}
            accessibilityRole="button"
            accessibilityState={{ selected, disabled: option.disabled }}
            disabled={option.disabled}
            onPress={() => onChange(option.value)}
            style={[styles.segment, selected && styles.segmentSelected, option.disabled && styles.dimmed]}>
            <Text style={[styles.segmentLabel, selected && styles.segmentLabelSelected]}>{option.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

export function Field({ label, error, ...props }: TextInputProps & { label: string; error?: string }) {
  return (
    <View style={styles.field}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <TextInput
        accessibilityLabel={label}
        placeholderTextColor={colors.textMuted}
        {...props}
        style={[styles.input, props.multiline && styles.inputMultiline, error ? styles.inputError : null]}
      />
      {error ? <Text style={styles.fieldError}>{error}</Text> : null}
    </View>
  );
}

export function Stepper({ value, onChange, min = 1, max = 50 }: { value: number; onChange: (value: number) => void; min?: number; max?: number }) {
  return (
    <View style={styles.stepper}>
      <Pressable accessibilityRole="button" accessibilityLabel="Scade cantitatea" disabled={value <= min} onPress={() => onChange(value - 1)} style={[styles.stepperButton, value <= min && styles.dimmed]}>
        <Text style={styles.stepperSign}>−</Text>
      </Pressable>
      <Text accessibilityLabel={`Cantitate ${value}`} style={styles.stepperValue}>
        {value}
      </Text>
      <Pressable accessibilityRole="button" accessibilityLabel="Crește cantitatea" disabled={value >= max} onPress={() => onChange(value + 1)} style={[styles.stepperButton, value >= max && styles.dimmed]}>
        <Text style={styles.stepperSign}>+</Text>
      </Pressable>
    </View>
  );
}

export function Centered({ children }: { children: ReactNode }) {
  return <View style={styles.centered}>{children}</View>;
}

export function LoadError({ message, onRetry }: { message: string; onRetry: () => void }) {
  return (
    <Centered>
      <AppText variant="bodyStrong" style={{ textAlign: 'center' }}>
        {message}
      </AppText>
      <Button label="Încearcă din nou" variant="outline" onPress={onRetry} />
    </Centered>
  );
}

const textStyles = StyleSheet.create({
  title: { fontFamily: fonts.heading, fontSize: 28, lineHeight: 32, color: colors.text },
  heading: { fontFamily: fonts.heading, fontSize: 21, lineHeight: 26, color: colors.text },
  body: { fontFamily: fonts.regular, fontSize: 15, lineHeight: 22, color: colors.textBody },
  bodyStrong: { fontFamily: fonts.semibold, fontSize: 15, lineHeight: 21, color: colors.text },
  muted: { fontFamily: fonts.regular, fontSize: 13, lineHeight: 18, color: colors.textMuted },
  price: { fontFamily: fonts.bold, fontSize: 15, color: colors.accent },
  small: { fontFamily: fonts.medium, fontSize: 12, color: colors.textMuted },
});

const styles = StyleSheet.create({
  button: {
    minHeight: 56,
    borderRadius: 16,
    paddingHorizontal: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  buttonPrimary: { backgroundColor: colors.primary },
  buttonOutline: { borderWidth: 1, borderColor: colors.border },
  buttonAccent: { backgroundColor: colors.accent },
  buttonLabel: { fontFamily: fonts.bold, fontSize: 16, color: colors.onPrimary },
  dimmed: { opacity: 0.6 },
  chip: { minHeight: minTouchSize, paddingHorizontal: 14, borderRadius: 22, justifyContent: 'center' },
  chipIdle: { borderWidth: 1, borderColor: colors.border },
  chipSelected: { borderWidth: 2, borderColor: colors.accent, backgroundColor: colors.accentSoft },
  chipFilterSelected: { backgroundColor: colors.primary },
  chipLabel: { fontFamily: fonts.medium, fontSize: 14, color: colors.text },
  segmented: { flexDirection: 'row', gap: 4, padding: 4, borderRadius: 14, backgroundColor: colors.surface },
  segment: { flex: 1, minHeight: minTouchSize, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  segmentSelected: { backgroundColor: colors.accent },
  segmentLabel: { fontFamily: fonts.medium, fontSize: 15, color: colors.text },
  segmentLabelSelected: { fontFamily: fonts.bold, color: colors.onAccent },
  field: { gap: 6 },
  fieldLabel: { fontFamily: fonts.semibold, fontSize: 14, color: colors.text },
  input: {
    minHeight: 48,
    paddingHorizontal: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    color: colors.text,
    fontFamily: fonts.regular,
    fontSize: 15,
  },
  inputMultiline: { minHeight: 72, paddingTop: 12, textAlignVertical: 'top' },
  inputError: { borderColor: colors.primary },
  fieldError: { fontFamily: fonts.medium, fontSize: 13, color: '#FF8A75' },
  stepper: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: colors.border, borderRadius: 16, height: 56 },
  stepperButton: { width: 48, height: 54, alignItems: 'center', justifyContent: 'center' },
  stepperSign: { fontFamily: fonts.bold, fontSize: 22, color: colors.text },
  stepperValue: { minWidth: 28, textAlign: 'center', fontFamily: fonts.bold, fontSize: 18, color: colors.text },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 16, padding: 24, backgroundColor: colors.background },
});
