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

console.log(1);

// window.Echo.private(`conversation.1`).listen(
//     ".Namu\\WireChat\\Events\\MessageCreated",
//     (e) => {
//         console.log("Message Body:", e.message.id);
//     }
// );

window.Echo.private(`chat.17`).listen(".App\\Events\\NewMessageSent", (e) => {
    console.log("Message:", e);
});

// window.Echo.private(`participant.4170705c4d6f64656c735c55736572.1`).listen(
//     ".Namu\\WireChat\\Events\\NotifyParticipant",
//     (e) => {
//         console.log(e);
//         console.log("Message Body:", e.message.body);
//     }
// );
