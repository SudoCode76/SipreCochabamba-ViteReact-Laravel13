import proj4 from "proj4";

export const PROJECT_UTM_CRS = "EPSG:32719";
export const PROJECT_GEOGRAPHIC_CRS = "EPSG:4326";
export const PROJECT_UTM_DEFINITION = "+proj=utm +zone=19 +south +datum=WGS84 +units=m +no_defs";

proj4.defs(PROJECT_UTM_CRS, PROJECT_UTM_DEFINITION);

function parseCoordinate(value) {
  if (value === null || value === undefined || String(value).trim() === "") {
    return null;
  }

  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

function isGeographic(latitude, longitude) {
  return latitude >= -90 && latitude <= 90 && longitude >= -180 && longitude <= 180;
}

function isUtm32719(northing, easting) {
  return northing >= 0 && northing <= 10_000_000 && easting >= 100_000 && easting <= 900_000;
}

export function normalizeProjectCoordinates(latitudeValue, longitudeValue, coordinateSystem = "") {
  const latitude = parseCoordinate(latitudeValue);
  const longitude = parseCoordinate(longitudeValue);
  const system = coordinateSystem || "";

  if (latitude === null || longitude === null) {
    return null;
  }

  if (system === "geographic" || (!system && isGeographic(latitude, longitude))) {
    return isGeographic(latitude, longitude) ? { lat: latitude, lng: longitude } : null;
  }

  if (system !== "utm_32719" && system !== "") {
    return null;
  }

  if (!isUtm32719(latitude, longitude)) {
    return null;
  }

  const [lng, lat] = proj4(PROJECT_UTM_CRS, PROJECT_GEOGRAPHIC_CRS, [longitude, latitude]);
  return isGeographic(lat, lng) ? { lat, lng } : null;
}
