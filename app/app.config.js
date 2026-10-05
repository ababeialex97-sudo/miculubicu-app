// Extends app.json with settings that come from files kept out of git.
/* global __dirname */
const fs = require('node:fs');
const path = require('node:path');

module.exports = ({ config }) => {
  // Firebase config for Android push. On EAS it comes from the GOOGLE_SERVICES_JSON file
  // environment variable; locally from ./google-services.json when present.
  const googleServicesFile = process.env.GOOGLE_SERVICES_JSON ?? (fs.existsSync(path.join(__dirname, 'google-services.json')) ? './google-services.json' : undefined);

  return {
    ...config,
    android: {
      ...config.android,
      ...(googleServicesFile ? { googleServicesFile } : {}),
    },
  };
};
