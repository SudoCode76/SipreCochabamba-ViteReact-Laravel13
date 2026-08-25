import assert from "node:assert/strict";
import test from "node:test";

import { loadListOrder, saveListOrder } from "./list-order.js";

test("guarda solo órdenes válidos y usa el valor predeterminado ante errores", () => {
  const values = new Map();
  globalThis.window = {
    localStorage: {
      getItem: (key) => values.get(key) ?? null,
      setItem: (key, value) => values.set(key, value),
    },
  };

  assert.equal(loadListOrder("projects"), "legacy");
  assert.equal(saveListOrder("projects", "recent"), true);
  assert.equal(loadListOrder("projects"), "recent");
  assert.equal(saveListOrder("items", "missing_specifications"), true);
  assert.equal(loadListOrder("items"), "missing_specifications");
  assert.equal(saveListOrder("projects", "invalid"), false);

  globalThis.window.localStorage.getItem = () => {
    throw new Error("storage unavailable");
  };
  assert.equal(loadListOrder("projects"), "legacy");
});
