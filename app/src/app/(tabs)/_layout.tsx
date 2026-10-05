import { Tabs } from 'expo-router';

import type { ColorValue } from 'react-native';

import { Icon, type IconName } from '@/components/icon';
import { colors, fonts } from '@/constants/theme';

function tabIcon(name: IconName) {
  return function TabIcon({ color }: { color: ColorValue }) {
    return <Icon name={name} color={color} />;
  };
}

export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        sceneStyle: { backgroundColor: colors.background },
        tabBarActiveTintColor: colors.accent,
        tabBarInactiveTintColor: colors.textMuted,
        tabBarLabelStyle: { fontFamily: fonts.semibold, fontSize: 12 },
        tabBarStyle: { backgroundColor: colors.bar, borderTopColor: colors.barBorder },
      }}>
      <Tabs.Screen name="index" options={{ title: 'Meniu', tabBarIcon: tabIcon('flame') }} />
      <Tabs.Screen name="comenzi" options={{ title: 'Comenzi', tabBarIcon: tabIcon('receipt') }} />
      <Tabs.Screen name="fidelitate" options={{ title: 'Fidelitate', tabBarIcon: tabIcon('gift') }} />
      <Tabs.Screen name="cont" options={{ title: 'Cont', tabBarIcon: tabIcon('user') }} />
    </Tabs>
  );
}
