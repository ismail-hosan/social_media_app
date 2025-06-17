import Echo from "laravel-echo";

import Pusher from "pusher-js";
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "https") === "https",
    enabledTransports: ["ws", "wss"],
});


// window.Echo.private(`chat.2`).listen("NewMessageSent", (e) => {
//     console.log("Message Body:", e.message.body);
// });

// window.Echo.private(`participant.4170705c4d6f64656c735c55736572.1`).listen(
//     ".Namu\\WireChat\\Events\\NotifyParticipant",
//     (e) => {
//         console.log(e);
//         console.log("Message Body:", e.message.body);
//     }
// );
