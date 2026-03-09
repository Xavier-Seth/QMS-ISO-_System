import { reactive, readonly } from "vue";

const state = reactive({
  open: false,
  title: "Confirm action",
  message: "Are you sure you want to continue?",
  confirmText: "Confirm",
  cancelText: "Cancel",
  tone: "danger", // danger | warning | primary
  loading: false,
  resolver: null,
});

function ask(options = {}) {
  state.open = true;
  state.title = options.title || "Confirm action";
  state.message = options.message || "Are you sure you want to continue?";
  state.confirmText = options.confirmText || "Confirm";
  state.cancelText = options.cancelText || "Cancel";
  state.tone = options.tone || "danger";
  state.loading = false;

  return new Promise((resolve) => {
    state.resolver = resolve;
  });
}

function confirm() {
  if (typeof state.resolver === "function") {
    state.resolver(true);
  }
  reset();
}

function cancel() {
  if (typeof state.resolver === "function") {
    state.resolver(false);
  }
  reset();
}

function setLoading(value) {
  state.loading = !!value;
}

function reset() {
  state.open = false;
  state.loading = false;
  state.resolver = null;
}

export function useConfirm() {
  return {
    state: readonly(state),
    ask,
    confirm,
    cancel,
    setLoading,
  };
}