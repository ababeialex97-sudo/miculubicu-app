// Extends app.json with settings that come from files kept out of git.
/* global __dirname */
const fs = require('node:fs');
const path = require('node:path');

module.exports = ({ config }) => {
  // Firebase config for Android push. On EAS it comes from the GOOGLE_SERVICES_JSON file
  // environment variable; locally from ./google-services.json when present.
  const googleServicesFile = process.env.GOOGLE_SERVICES_JSON ?? (fs.existsSync(path.join(__dirname, 'google-services.json')) ? './google-services.json' : undefined);

  // "Continuă cu Google" on iOS needs the iOS OAuth client's reversed ID as a URL scheme.
  // Without the client ID the native plugin is left out and the app hides the button.
  const googleIosClientId = process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID;
  const googlePlugin = googleIosClientId
    ? [['@react-native-google-signin/google-signin', { iosUrlScheme: `com.googleusercontent.apps.${googleIosClientId.replace('.apps.googleusercontent.com', '')}` }]]
    : [];

  return {
    ...config,
    android: {
      ...config.android,
      ...(googleServicesFile ? { googleServicesFile } : {}),
    },
    plugins: [...(config.plugins ?? []), ...googlePlugin],
  };
};
