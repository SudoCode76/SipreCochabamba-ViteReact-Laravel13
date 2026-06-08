import { useCallback, useEffect, useRef, useState } from "react";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import "proj4leaflet";

import { Button } from "@/components/ui/button";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
});

const COCHABAMBA_CENTER = [-17.416128493780963, -66.16543579646086];
const DEFAULT_ZOOM = 12;
const MIN_ZOOM = 8;
const MAX_ZOOM = 12;
const CRS_CODE = "EPSG:32719";
const CRS_DEF = "+proj=utm +zone=19 +south +datum=WGS84 +units=m +no_defs";
const CRS_RESOLUTIONS = [1600, 800, 400, 200, 100, 50, 25, 10, 5, 2.5, 1, 0.5, 0.25, 0.125, 0.0625];
const MUNICIPAL_WMS = {
  imagenes: "https://busquedasgamc.cochabamba.bo/web/index.php?r=services/get-imagenes",
  calles: "https://busquedasgamc.cochabamba.bo/web/index.php?r=services/get-nombre-calles",
  catastro: "https://busquedasgamc.cochabamba.bo/web/index.php?r=services/get-info-catastro",
  featureInfo: "https://busquedasgamc.cochabamba.bo/web/index.php?r=services/get-feature-info-url-calles",
};

function parseLatLng(value) {
  const latitud = Number(value?.latitud);
  const longitud = Number(value?.longitud);

  if (!Number.isFinite(latitud) || !Number.isFinite(longitud)) {
    return null;
  }

  return L.latLng(latitud, longitud);
}

function formatLatLng(latlng) {
  if (!latlng) {
    return "-";
  }

  return `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`;
}

function getFeatureInfoUrl(map, crs, layerUrl, latlng, params) {
  const point = map.latLngToContainerPoint(latlng, map.getZoom());
  const size = map.getSize();
  const bounds = map.getBounds();
  const sw = crs.projection._proj.forward([bounds.getSouthWest().lng, bounds.getSouthWest().lat]);
  const ne = crs.projection._proj.forward([bounds.getNorthEast().lng, bounds.getNorthEast().lat]);

  const defaultParams = {
    request: "GetFeatureInfo",
    service: "WMS",
    srs: CRS_CODE,
    styles: "",
    version: "1.1.1",
    format: "image/png",
    bbox: [sw.join(","), ne.join(",")].join(","),
    height: size.y,
    width: size.x,
    layers: 0,
    query_layers: 0,
  };

  const requestParams = L.Util.extend(defaultParams, params || {});
  requestParams[requestParams.version === "1.3.0" ? "i" : "x"] = point.x;
  requestParams[requestParams.version === "1.3.0" ? "j" : "y"] = point.y;

  return layerUrl + L.Util.getParamString(requestParams, layerUrl, true);
}

function readTerritorialResponse(data) {
  if (!data || data.status !== true || !data.response) {
    return null;
  }

  return {
    zona: data.response.zona_tributari || "",
    distrito: data.response.distrito || "",
    subdistrito: data.response.subdistrito || "",
    otb: data.response.otb || data.response.OTB || data.response.nombre_otb || "",
  };
}

export default function ProjectLocationMap({ value, onChange }) {
  const mapElementRef = useRef(null);
  const mapRef = useRef(null);
  const markerRef = useRef(null);
  const crsRef = useRef(null);
  const latestOnChangeRef = useRef(onChange);
  const initialValueRef = useRef(value);
  const [mouseLocation, setMouseLocation] = useState("-");
  const [scale, setScale] = useState("-");
  const [territorialWarning, setTerritorialWarning] = useState("");

  useEffect(() => {
    latestOnChangeRef.current = onChange;
  }, [onChange]);

  const clearTerritorialData = useCallback((message) => {
    setTerritorialWarning(message);
    latestOnChangeRef.current?.({
      distrito: "",
      zona: "",
      subdistrito: "",
      otb: "",
    });
  }, []);

  const fetchTerritorialData = useCallback(async (latlng) => {
    const map = mapRef.current;
    const crs = crsRef.current;

    if (!map || !crs) {
      return;
    }

    const url = getFeatureInfoUrl(
      map,
      crs,
      MUNICIPAL_WMS.featureInfo,
      latlng,
      {
        INFO_FORMAT: "application/json",
        FEATURE_COUNT: 50,
      },
    );

    try {
      const response = await fetch(url);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      const territorialData = readTerritorialResponse(data);

      if (!territorialData) {
        clearTerritorialData("Seleccione una ubicación válida dentro del municipio.");
        return;
      }

      setTerritorialWarning("");
      latestOnChangeRef.current?.(territorialData);
    } catch (error) {
      console.warn("No se pudieron obtener los datos territoriales del punto seleccionado.", {
        url,
        error,
      });
      clearTerritorialData("Seleccione una ubicación válida dentro del municipio.");
    }
  }, [clearTerritorialData]);

  const updateLocation = useCallback((latlng) => {
    latestOnChangeRef.current?.({
      latitud: latlng.lat.toFixed(6),
      longitud: latlng.lng.toFixed(6),
    });

    fetchTerritorialData(latlng);
  }, [fetchTerritorialData]);

  const createOrMoveMarker = useCallback((latlng, shouldUpdateFields = true) => {
    const map = mapRef.current;

    if (!map) {
      return;
    }

    if (markerRef.current) {
      markerRef.current.setLatLng(latlng);
    } else {
      markerRef.current = L.marker(latlng, { draggable: true }).addTo(map);
      markerRef.current.bindPopup("<b>Ubicación Seleccionada</b>");
      markerRef.current.on("dragend", (event) => {
        updateLocation(event.target.getLatLng());
      });
    }

    markerRef.current.openPopup();

    if (shouldUpdateFields) {
      updateLocation(latlng);
    }
  }, [updateLocation]);

  useEffect(() => {
    if (!mapElementRef.current || !L?.Proj?.CRS) {
      setTerritorialWarning("No se pudieron cargar las librerías del mapa.");
      return undefined;
    }

    const crs = new L.Proj.CRS(CRS_CODE, CRS_DEF, {
      resolutions: CRS_RESOLUTIONS,
    });
    crsRef.current = crs;

    const map = new L.Map(mapElementRef.current, {
      crs,
      continuousWorld: true,
      worldCopyJump: false,
      minZoom: MIN_ZOOM,
      maxZoom: MAX_ZOOM,
      layers: [
        L.tileLayer.wms(MUNICIPAL_WMS.imagenes, {
          layers: "0",
          format: "image/png",
          opacity: 0.9,
          version: "1.1.1",
        }),
      ],
    });

    L.tileLayer.wms(MUNICIPAL_WMS.calles, {
      layers: "0",
      format: "image/png",
      continuousWorld: true,
      transparent: true,
      opacity: 1,
      version: "1.1.1",
    }).addTo(map);

    L.tileLayer.wms(MUNICIPAL_WMS.catastro, {
      layers: "0",
      format: "image/png",
      version: "1.1.1",
      transparent: true,
    }).addTo(map);

    mapRef.current = map;
    const initialLatLng = parseLatLng(initialValueRef.current) || L.latLng(COCHABAMBA_CENTER);
    map.setView(initialLatLng, DEFAULT_ZOOM);
    createOrMoveMarker(initialLatLng, !parseLatLng(initialValueRef.current));

    const updateScale = () => {
      const resolution = CRS_RESOLUTIONS[map.getZoom()];
      setScale(Number.isFinite(resolution) ? `1:${Math.round(resolution * 96 * 39.37).toLocaleString("es-BO")}` : "-");
    };

    updateScale();
    map.on("zoomend", updateScale);
    map.on("mousemove", (event) => setMouseLocation(formatLatLng(event.latlng)));
    map.on("click", (event) => createOrMoveMarker(event.latlng, true));

    requestAnimationFrame(() => map.invalidateSize());

    return () => {
      map.remove();
      mapRef.current = null;
      markerRef.current = null;
      crsRef.current = null;
    };
  }, [createOrMoveMarker]);

  useEffect(() => {
    const currentLatLng = parseLatLng(value);
    const markerLatLng = markerRef.current?.getLatLng();

    if (!currentLatLng || !markerLatLng) {
      return;
    }

    if (
      Math.abs(currentLatLng.lat - markerLatLng.lat) > 0.000001
      || Math.abs(currentLatLng.lng - markerLatLng.lng) > 0.000001
    ) {
      createOrMoveMarker(currentLatLng, false);
    }
  }, [createOrMoveMarker, value]);

  const resetToCochabamba = () => {
    const map = mapRef.current;
    if (!map) {
      return;
    }

    const center = L.latLng(COCHABAMBA_CENTER);
    createOrMoveMarker(center, true);
    map.setView(center, DEFAULT_ZOOM);
  };

  return (
    <div className="space-y-3">
      <div className="relative overflow-hidden rounded-xl border border-border/80 bg-muted/30">
        <div ref={mapElementRef} id="map" className="h-[360px] w-full" />

        <div id="wrapper" className="pointer-events-none absolute bottom-3 left-3 right-3 z-[450] flex flex-wrap items-center justify-between gap-2 text-xs">
          <span id="location" className="rounded-full bg-background/95 px-3 py-1.5 text-muted-foreground shadow-sm">
            {mouseLocation}
          </span>
          <span id="scale" className="rounded-full bg-background/95 px-3 py-1.5 text-muted-foreground shadow-sm">
            Escala {scale}
          </span>
        </div>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm text-muted-foreground">
          Haz clic sobre el mapa para seleccionar el punto. Puedes arrastrar el marcador para corregir la ubicación.
        </p>
        <Button type="button" variant="outline" className="rounded-full" onClick={resetToCochabamba}>
          Centrar en Cochabamba
        </Button>
      </div>

      {territorialWarning && (
        <p className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
          {territorialWarning}
        </p>
      )}
    </div>
  );
}
