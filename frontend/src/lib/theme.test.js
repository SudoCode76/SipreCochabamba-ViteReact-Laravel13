import assert from "node:assert/strict";
import test from "node:test";

import { normalizeTheme, resolveTheme } from "./theme.js";

test("resuelve preferencias explícitas y del sistema", () => {
  assert.equal(resolveTheme("light", true), "light");
  assert.equal(resolveTheme("dark", false), "dark");
  assert.equal(resolveTheme("system", true), "dark");
  assert.equal(resolveTheme("system", false), "light");
  assert.equal(normalizeTheme("desconocido"), "system");
});
