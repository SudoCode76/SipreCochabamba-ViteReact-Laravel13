import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import "ol/ol.css";

import Feature from "ol/Feature.js";
import Map from "ol/Map.js";
import View from "ol/View.js";
import Point from "ol/geom/Point.js";
import Modify from "ol/interaction/Modify.js";
import TileLayer from "ol/layer/Tile.js";
import VectorLayer from "ol/layer/Vector.js";
import { get as getProjection } from "ol/proj.js";
import { register } from "ol/proj/proj4.js";
import TileWMS from "ol/source/TileWMS.js";
import VectorSource from "ol/source/Vector.js";
import { Icon, Style } from "ol/style.js";
import proj4 from "proj4";

import { Button } from "@/components/ui/button";

const GIS_BASE_URL = "http://192.168.105.219:6080/arcgis/services";
const PROJECTION_CODE = "EPSG:32719";
const LEGACY_BOUNDS = [
  788396.9511477941,
  8059108.1417208575,
  811711.388216491,
  8090058.166346149,
];

const WMS_PARAMS = {
  FORMAT: "image/png",
  VERSION: "1.1.1",
  STYLES: "",
};

let projectionRegistered = false;

function ensureProjection() {
  if (!projectionRegistered) {
    proj4.defs(PROJECTION_CODE, "+proj=utm +zone=19 +south +datum=WGS84 +units=m +no_defs +type=crs");
    register(proj4);
    projectionRegistered = true;
  }

  const projection = getProjection(PROJECTION_CODE);
  projection?.setExtent(LEGACY_BOUNDS);

  return projection;
}

function createWmsLayer({ name, url, layers, visible = true, opacity = 1, transparent = true }) {
  const source = new TileWMS({
    url,
    params: {
      ...WMS_PARAMS,
      LAYERS: layers,
      TRANSPARENT: transparent,
    },
    serverType: "geoserver",
    crossOrigin: "anonymous",
  });

  return {
    name,
    source,
    layer: new TileLayer({
      source,
      visible,
      opacity,
    }),
  };
}

function normalizeText(value) {
  return String(value ?? "").trim();
}

function readProperty(properties, candidates) {
  if (!properties) {
    return "";
  }

  const normalizedEntries = Object.entries(properties).map(([key, value]) => [
    key.toLowerCase().replace(/[_\s.:-]/g, ""),
    value,
  ]);

  for (const candidate of candidates) {
    const normalizedCandidate = candidate.toLowerCase().replace(/[_\s.:-]/g, "");
    const found = normalizedEntries.find(([key]) => key.includes(normalizedCandidate));

    if (found && normalizeText(found[1])) {
      return normalizeText(found[1]);
    }
  }

  return "";
}

function readFromJson(json, candidates) {
  const features = Array.isArray(json?.features) ? json.features : [];

  for (const feature of features) {
    const value = readProperty(feature.properties || feature.attributes, candidates);

    if (value) {
      return value;
    }
  }

  return readProperty(json?.properties || json?.attributes, candidates);
}

function readFromText(text, candidates) {
  for (const candidate of candidates) {
    const escaped = candidate.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    const patterns = [
      new RegExp(`"${escaped}"\\s*:\\s*"([^"]+)"`, "i"),
      new RegExp(`"${escaped}"\\s*:\\s*([^,}\\]]+)`, "i"),
      new RegExp(`${escaped}\\s*[:=]\\s*"?([^",}\\]\\n]+)"?`, "i"),
    ];

    for (const pattern of patterns) {
      const match = text.match(pattern);
      const value = normalizeText(match?.[1]).replace(/^"|"$/g, "");

      if (value) {
        return value;
      }
    }
  }

  return "";
}

function extractField(payload, candidates) {
  if (!payload) {
    return "";
  }

  if (typeof payload === "string") {
    try {
      return readFromJson(JSON.parse(payload), candidates) || readFromText(payload, candidates);
    } catch {
      return readFromText(payload, candidates);
    }
  }

  return readFromJson(payload, candidates);
}

function coordinateFromValue(value) {
  const x = Number(value?.longitud);
  const y = Number(value?.latitud);

  return Number.isFinite(x) && Number.isFinite(y) ? [x, y] : null;
}

function formatCoordinate(coordinate) {
  if (!coordinate) {
    return "-";
  }

  return `${coordinate[0].toFixed(5)}, ${coordinate[1].toFixed(5)}`;
}

function scaleFromResolution(resolution) {
  if (!Number.isFinite(resolution) || resolution <= 0) {
    return "-";
  }

  return `1:${Math.round(resolution * 96 * 39.37).toLocaleString("es-BO")}`;
}

export default function ProjectLocationMap({ value, onChange }) {
  const mapElementRef = useRef(null);
  const mapRef = useRef(null);
  const markerFeatureRef = useRef(null);
  const vectorSourceRef = useRef(null);
  const modifyInteractionRef = useRef(null);
  const layersRef = useRef(null);
  const latestValueRef = useRef(value);
  const latestOnChangeRef = useRef(onChange);
  const [contextMenu, setContextMenu] = useState(null);
  const [mouseLocation, setMouseLocation] = useState("-");
  const [scale, setScale] = useState("-");
  const [notice, setNotice] = useState(null);

  const projection = useMemo(() => ensureProjection(), []);

  useEffect(() => {
    latestValueRef.current = value;
    latestOnChangeRef.current = onChange;
  }, [value, onChange]);

  const fetchFeatureInfo = useCallback(async (layerInfo, coordinate) => {
    const map = mapRef.current;
    if (!map || !layerInfo?.source) {
      return "";
    }

    const view = map.getView();
    const url = layerInfo.source.getFeatureInfoUrl(
      coordinate,
      view.getResolution(),
      view.getProjection(),
      {
        INFO_FORMAT: "application/json",
        FEATURE_COUNT: 50,
      },
    );

    if (!url) {
      return "";
    }

    const response = await fetch(url);
    return response.text();
  }, []);

  const queryTerritorialData = useCallback(async (coordinate) => {
    const layers = layersRef.current;
    if (!layers) {
      return;
    }

    setNotice(null);

    try {
      const [limitesText, otbsText] = await Promise.all([
        fetchFeatureInfo(layers.limites, coordinate),
        fetchFeatureInfo(layers.otbs, coordinate),
        fetchFeatureInfo(layers.predios, coordinate).catch(() => ""),
        fetchFeatureInfo(layers.vias, coordinate).catch(() => ""),
      ]);

      const distrito = extractField(limitesText, ["distrito", "Distrito"]);
      const zona = extractField(limitesText, ["Zona", "zona", "nombre_zona", "SubDistrito"]);
      const otb = extractField(otbsText, ["OTB", "otb", "nombre_otb"]);

      latestOnChangeRef.current?.({
        ...(distrito ? { distrito } : {}),
        ...(zona ? { zona } : {}),
        ...(otb ? { otb } : {}),
      });

      if (!distrito && !zona && !otb) {
        setNotice("No se encontraron datos territoriales para el punto seleccionado.");
      }
    } catch {
      setNotice("No se pudieron obtener los datos territoriales del punto seleccionado.");
    }
  }, [fetchFeatureInfo]);

  const updateLocation = useCallback((coordinate, shouldQuery = true) => {
    latestOnChangeRef.current?.({
      latitud: coordinate[1].toFixed(5),
      longitud: coordinate[0].toFixed(5),
    });

    if (shouldQuery) {
      void queryTerritorialData(coordinate);
    }
  }, [queryTerritorialData]);

  const createOrMoveMarker = useCallback((coordinate, shouldQuery = true) => {
    const vectorSource = vectorSourceRef.current;
    const map = mapRef.current;

    if (!vectorSource || !map) {
      return;
    }

    if (!markerFeatureRef.current) {
      const feature = new Feature({
        geometry: new Point(coordinate),
      });
      feature.set("posicion_marcador", "posicion_marcador");
      markerFeatureRef.current = feature;
      vectorSource.clear();
      vectorSource.addFeature(feature);

      if (modifyInteractionRef.current) {
        map.removeInteraction(modifyInteractionRef.current);
      }

      const modifyInteraction = new Modify({
        source: vectorSource,
      });
      modifyInteraction.on("modifyend", (event) => {
        const feature = event.features.item(0);
        const nextCoordinate = feature?.getGeometry()?.getCoordinates();
        if (nextCoordinate) {
          updateLocation(nextCoordinate, true);
        }
      });
      map.addInteraction(modifyInteraction);
      modifyInteractionRef.current = modifyInteraction;
    } else {
      markerFeatureRef.current.getGeometry().setCoordinates(coordinate);
    }

    updateLocation(coordinate, shouldQuery);
  }, [updateLocation]);

  const handleContextMenu = useCallback((event) => {
    event.preventDefault();

    const map = mapRef.current;
    if (!map) {
      return;
    }

    setContextMenu({
      pixel: map.getEventPixel(event),
      coordinate: map.getEventCoordinate(event),
      left: event.offsetX,
      top: event.offsetY,
    });
  }, []);

  useEffect(() => {
    if (!mapElementRef.current || !projection) {
      return undefined;
    }

    const capaBase = createWmsLayer({
      name: "Imagen base",
      url: `${GIS_BASE_URL}/imagenes/imagen2019_500/MapServer/WMSServer`,
      layers: "0",
      transparent: false,
    });
    const capaLimites = createWmsLayer({
      name: "Limites",
      url: `${GIS_BASE_URL}/planificacion/limites/MapServer/WMSServer`,
      layers: "0,1,2,3,4",
      opacity: 0.9,
    });
    const capaOtbs = createWmsLayer({
      name: "OTBs",
      url: `${GIS_BASE_URL}/planificacion/OTBS/MapServer/WMSServer`,
      layers: "0",
      opacity: 0.75,
    });
    const capaManzanas = createWmsLayer({
      name: "Manzanas",
      url: `${GIS_BASE_URL}/catastro/manzanasWms/MapServer/WMSServer`,
      layers: "0",
      opacity: 0.8,
    });
    const capaPredios = createWmsLayer({
      name: "Predios",
      url: `${GIS_BASE_URL}/catastro/predios_cba/MapServer/WMSServer`,
      layers: "0",
      opacity: 0.7,
    });
    const capaVias = createWmsLayer({
      name: "Vias",
      url: `${GIS_BASE_URL}/planificacion/viasWms/MapServer/WMSServer`,
      layers: "0",
      opacity: 0.9,
    });

    layersRef.current = {
      limites: capaLimites,
      otbs: capaOtbs,
      predios: capaPredios,
      vias: capaVias,
    };

    [
      capaBase,
      capaLimites,
      capaOtbs,
      capaManzanas,
      capaPredios,
      capaVias,
    ].forEach(({ name, source }) => {
      source.on("tileloaderror", () => {
        setNotice(`No se pudo cargar la capa GIS: ${name}.`);
      });
    });

    const vectorSource = new VectorSource();
    vectorSourceRef.current = vectorSource;

    const markerLayer = new VectorLayer({
      source: vectorSource,
      style: new Style({
        image: new Icon({
          src: "/img/pre_marcador.png",
          anchor: [0.5, 0.85],
          scale: 1,
        }),
      }),
    });

    const view = new View({
      projection,
      center: [(LEGACY_BOUNDS[0] + LEGACY_BOUNDS[2]) / 2, (LEGACY_BOUNDS[1] + LEGACY_BOUNDS[3]) / 2],
      zoom: 12,
    });

    const map = new Map({
      target: mapElementRef.current,
      layers: [
        capaBase.layer,
        capaLimites.layer,
        capaOtbs.layer,
        capaManzanas.layer,
        capaPredios.layer,
        capaVias.layer,
        markerLayer,
      ],
      view,
      controls: [],
    });

    mapRef.current = map;
    requestAnimationFrame(() => {
      map.updateSize();
      view.fit(LEGACY_BOUNDS, { size: map.getSize() });
      view.setResolution(view.getResolution() / 17.5);
      setScale(scaleFromResolution(view.getResolution()));

      const initialCoordinate = coordinateFromValue(latestValueRef.current);
      if (initialCoordinate) {
        createOrMoveMarker(initialCoordinate, false);
        view.setCenter(initialCoordinate);
      }
    });

    const updateResolution = () => setScale(scaleFromResolution(view.getResolution()));
    view.on("change:resolution", updateResolution);

    map.on("pointermove", (event) => {
      setMouseLocation(formatCoordinate(event.coordinate));
    });

    map.getViewport().addEventListener("contextmenu", handleContextMenu);

    return () => {
      map.getViewport().removeEventListener("contextmenu", handleContextMenu);
      view.un("change:resolution", updateResolution);
      map.setTarget(undefined);
      mapRef.current = null;
      vectorSourceRef.current = null;
      markerFeatureRef.current = null;
      modifyInteractionRef.current = null;
      layersRef.current = null;
    };
  }, [createOrMoveMarker, handleContextMenu, projection]);

  useEffect(() => {
    const currentCoordinate = coordinateFromValue(value);
    const markerCoordinate = markerFeatureRef.current?.getGeometry()?.getCoordinates();

    if (!currentCoordinate || !markerCoordinate) {
      return;
    }

    if (
      Math.abs(currentCoordinate[0] - markerCoordinate[0]) > 0.00001
      || Math.abs(currentCoordinate[1] - markerCoordinate[1]) > 0.00001
    ) {
      createOrMoveMarker(currentCoordinate, false);
    }
  }, [createOrMoveMarker, value]);

  const centerHere = () => {
    if (contextMenu?.coordinate) {
      mapRef.current?.getView().setCenter(contextMenu.coordinate);
    }

    setContextMenu(null);
  };

  const createMarkerHere = () => {
    if (contextMenu?.coordinate) {
      createOrMoveMarker(contextMenu.coordinate, true);
    }

    setContextMenu(null);
  };

  return (
    <div className="space-y-3">
      <div className="relative overflow-hidden rounded-xl border border-border/80 bg-muted/30">
        <div ref={mapElementRef} id="map" className="h-[360px] w-full" />

        <div id="popup" className="hidden">
          <a href="#popup" id="popup-closer">Cerrar</a>
          <div id="popup-content" />
        </div>

        <div id="wrapper" className="absolute bottom-3 left-3 right-3 flex flex-wrap items-center justify-between gap-2 text-xs">
          <span id="location" className="rounded-full bg-background/95 px-3 py-1.5 text-muted-foreground shadow-sm">
            {mouseLocation}
          </span>
          <span id="scale" className="rounded-full bg-background/95 px-3 py-1.5 text-muted-foreground shadow-sm">
            Escala {scale}
          </span>
        </div>

        {contextMenu && (
          <div
            className="absolute z-20 w-[180px] overflow-hidden rounded-xl border border-border/80 bg-background/98 p-1 text-sm shadow-xl"
            style={{ left: contextMenu.left, top: contextMenu.top }}
          >
            <button type="button" className="block w-full rounded-lg px-3 py-2 text-left hover:bg-muted" onClick={centerHere}>
              Centrar aquí
            </button>
            <button type="button" className="block w-full rounded-lg px-3 py-2 text-left hover:bg-muted" onClick={createMarkerHere}>
              Crear marcador
            </button>
          </div>
        )}
      </div>

      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm text-muted-foreground">
          Clic derecho sobre el mapa para crear o reemplazar el marcador.
        </p>
        <Button type="button" variant="outline" className="rounded-full" onClick={() => mapRef.current?.getView().fit(LEGACY_BOUNDS, { size: mapRef.current?.getSize() })}>
          Ver extensión inicial
        </Button>
      </div>

      {notice && (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          {notice}
        </div>
      )}
    </div>
  );
}
