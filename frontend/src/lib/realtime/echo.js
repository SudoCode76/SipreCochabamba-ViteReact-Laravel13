import Echo from "laravel-echo";
import Pusher from "pusher-js";

import apiClient from "@/lib/api/client";

let echoInstance = null;
let missingConfigReported = false;

export function getEcho() {
  const key = import.meta.env.VITE_REVERB_APP_KEY;
  const url = import.meta.env.VITE_REVERB_URL;

  if (!key || !url) {
    if (!missingConfigReported) {
      console.error("Notificaciones en tiempo real deshabilitadas: falta configurar Reverb.", {
        hasKey: Boolean(key),
        hasUrl: Boolean(url),
      });
      missingConfigReported = true;
    }

    return null;
  }

  if (echoInstance) {
    return echoInstance;
  }

  const reverbUrl = new URL(url, window.location.origin);
  const secure = reverbUrl.protocol === "https:";

  try {
    window.Pusher = Pusher;
    echoInstance = new Echo({
      broadcaster: "reverb",
      key,
      wsHost: reverbUrl.hostname,
      wsPort: Number(reverbUrl.port || (secure ? 443 : 80)),
      wssPort: Number(reverbUrl.port || 443),
      forceTLS: secure,
      enabledTransports: ["ws", "wss"],
      channelAuthorization: {
        customHandler: ({ socketId, channelName }, callback) => {
          apiClient.post("/broadcasting/auth", {
            socket_id: socketId,
            channel_name: channelName,
          }).then(({ data }) => callback(null, data))
            .catch((error) => callback(error, null));
        },
      },
    });
  } catch (error) {
    echoInstance = null;
    console.error("No se pudo iniciar la conexión de notificaciones en tiempo real.", error);
  }

  return echoInstance;
}

export function disconnectEcho() {
  echoInstance?.disconnect();
  echoInstance = null;
}
