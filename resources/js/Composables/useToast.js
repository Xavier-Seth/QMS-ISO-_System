import { reactive, readonly } from "vue";

const state = reactive({
  items: [],
});

let seed = 1;

function remove(id) {
  const index = state.items.findIndex((item) => item.id === id);
  if (index !== -1) {
    state.items.splice(index, 1);
  }
}

function push({
  type = "info",
  title = "",
  message = "",
  duration = 4000,
}) {
  const id = seed++;

  const item = {
    id,
    type,
    title,
    message,
    duration,
  };

  state.items.push(item);

  if (duration > 0) {
    window.setTimeout(() => {
      remove(id);
    }, duration);
  }

  return id;
}

function success(message, title = "Success", duration = 4000) {
  return push({ type: "success", title, message, duration });
}

function error(message, title = "Error", duration = 5000) {
  return push({ type: "error", title, message, duration });
}

function info(message, title = "Notice", duration = 3500) {
  return push({ type: "info", title, message, duration });
}

function clear() {
  state.items.splice(0, state.items.length);
}

export function useToast() {
  return {
    toasts: readonly(state.items),
    push,
    remove,
    clear,
    success,
    error,
    info,
  };
}