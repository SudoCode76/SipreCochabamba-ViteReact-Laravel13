import assert from "node:assert/strict";
import test from "node:test";

import { normalizeProjectCoordinates } from "./project-coordinates.js";

test("normaliza coordenadas geográficas, UTM y valores inválidos", () => {
  assert.deepEqual(normalizeProjectCoordinates("-17.389500", "-66.156800"), {
    lat: -17.3895,
    lng: -66.1568,
  });

  const validTemplates = [
    ["8071365.157997101", "799350.8635736719"],
    ["8073902.354774009", "799908.8732260253"],
    ["8070512.617949993", "799169.2982296118"],
    ["8069046.748299071", "801104.5586863053"],
  ];

  for (const [northing, easting] of validTemplates) {
    const coordinates = normalizeProjectCoordinates(northing, easting, null);
    assert.ok(coordinates.lat > -18 && coordinates.lat < -17);
    assert.ok(coordinates.lng > -67 && coordinates.lng < -65);
  }

  assert.equal(normalizeProjectCoordinates("", ""), null);
  assert.equal(normalizeProjectCoordinates("8071365.15800", "21.000003123"), null);
});
