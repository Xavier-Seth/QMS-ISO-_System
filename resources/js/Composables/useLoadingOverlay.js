import { computed, reactive } from "vue";

const state = reactive({
  visible: false,
  message: "Please wait...",
  lockCount: 0,
});

function show(message = "Please wait...") {
  state.message = message;
  state.visible = true;
  state.lockCount += 1;
}

function hide() {
  state.lockCount = Math.max(0, state.lockCount - 1);

  if (state.lockCount === 0) {
    state.visible = false;
    state.message = "Please wait...";
  }
}

function setMessage(message) {
  state.message = message || "Please wait...";
}

function open(message = "Please wait...") {
  state.message = message;
  state.visible = true;
  state.lockCount = 1;
}

function close() {
  state.visible = false;
  state.message = "Please wait...";
  state.lockCount = 0;
}

export function useLoadingOverlay() {
  return {
    state: computed(() => state),
    isVisible: computed(() => state.visible),
    message: computed(() => state.message),
    show,
    hide,
    setMessage,
    open,
    close,
  };
}