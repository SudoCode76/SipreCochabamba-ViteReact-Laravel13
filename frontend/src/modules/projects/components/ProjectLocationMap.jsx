import { useCallback, useEffect, useRef, useState } from "react";
import "ol/ol.css";

import Feature from "ol/Feature.js";
import Map from "ol/Map.js";
import View from "ol/View.js";
import Point from "ol/geom/Point.js";
import Modify from "ol/interaction/Modify.js";
import TileLayer from "ol/layer/Tile.js";
import VectorLayer from "ol/layer/Vector.js";
import OSM from "ol/source/OSM.js";
import VectorSource from "ol/source/Vector.js";
import { Circle, Fill, Stroke, Style } from "ol/style.js";

import { Button } from "@/components/ui/button";

const COCHABAMBA_LON_LAT = [-66.1568, -17.3895];
const DEFAULT_ZOOM = 13;
const WEB_MERCATOR_HALF_WORLD = 20037508.34;

function lonLatToWebMercator([longitud, latitud]) {
  const x = (longitud * WEB_MERCATOR_HALF_WORLD) / 180;
  let y = Math.log(Math.tan(((90 + latitud) * Math.PI) / 360)) / (Math.PI / 180);
  y = (y * WEB_MERCATOR_HALF_WORLD) / 180;

  return [x, y];
}

function webMercatorToLonLat([x, y]) {
  const longitud = (x / WEB_MERCATOR_HALF_WORLD) * 180;
  let latitud = (y / WEB_MERCATOR_HALF_WORLD) * 180;
  latitud = (180 / Math.PI) * (2 * Math.atan(Math.exp((latitud * Math.PI) / 180)) - Math.PI / 2);

  return [longitud, latitud];
}

const COCHABAMBA_CENTER = lonLatToWebMercator(COCHABAMBA_LON_LAT);

function parseCoordinate(value) {
  const latitud = Number(value?.latitud);
  const longitud = Number(value?.longitud);

  if (!Number.isFinite(latitud) || !Number.isFinite(longitud)) {
    return null;
  }

  return lonLatToWebMercator([longitud, latitud]);
}

function formatLonLat(coordinate) {
  if (!coordinate) {
    return "-";
  }

  const [longitud, latitud] = webMercatorToLonLat(coordinate);
  return `${latitud.toFixed(6)}, ${longitud.toFixed(6)}`;
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
  const initialValueRef = useRef(value);
  const latestOnChangeRef = useRef(onChange);
  const [mouseLocation, setMouseLocation] = useState("-");
  const [scale, setScale] = useState("-");

  useEffect(() => {
    latestOnChangeRef.current = onChange;
  }, [onChange]);

  const updateLocation = useCallback((coordinate) => {
    const [longitud, latitud] = webMercatorToLonLat(coordinate);

    latestOnChangeRef.current?.({
      latitud: latitud.toFixed(6),
      longitud: longitud.toFixed(6),
    });
  }, []);

  const createOrMoveMarker = useCallback((coordinate, shouldUpdateFields = true) => {
    const map = mapRef.current;
    const vectorSource = vectorSourceRef.current;

    if (!map || !vectorSource) {
      return;
    }

    if (!markerFeatureRef.current) {
      const markerFeature = new Feature({
        geometry: new Point(coordinate),
      });

      markerFeature.set("posicion_marcador", "posicion_marcador");
      markerFeatureRef.current = markerFeature;
      vectorSource.clear();
      vectorSource.addFeature(markerFeature);

      if (modifyInteractionRef.current) {
        map.removeInteraction(modifyInteractionRef.current);
      }

      const modifyInteraction = new Modify({ source: vectorSource });
      modifyInteraction.on("modifyend", (event) => {
        const feature = event.features.item(0);
        const nextCoordinate = feature?.getGeometry()?.getCoordinates();

        if (nextCoordinate) {
          updateLocation(nextCoordinate);
        }
      });

      map.addInteraction(modifyInteraction);
      modifyInteractionRef.current = modifyInteraction;
    } else {
      markerFeatureRef.current.getGeometry().setCoordinates(coordinate);
    }

    if (shouldUpdateFields) {
      updateLocation(coordinate);
    }
  }, [updateLocation]);

  useEffect(() => {
    if (!mapElementRef.current) {
      return undefined;
    }

    const vectorSource = new VectorSource();
    vectorSourceRef.current = vectorSource;

    const markerLayer = new VectorLayer({
      source: vectorSource,
      style: new Style({
        image: new Circle({
          radius: 8,
          fill: new Fill({ color: "#059669" }),
          stroke: new Stroke({ color: "#ffffff", width: 3 }),
        }),
      }),
    });

    const view = new View({
      projection: "EPSG:3857",
      center: COCHABAMBA_CENTER,
      zoom: DEFAULT_ZOOM,
    });

    const map = new Map({
      target: mapElementRef.current,
      layers: [
        new TileLayer({
          source: new OSM(),
        }),
        markerLayer,
      ],
      view,
    });

    mapRef.current = map;

    const initialCoordinate = parseCoordinate(initialValueRef.current) || COCHABAMBA_CENTER;
    view.setCenter(initialCoordinate);
    view.setZoom(DEFAULT_ZOOM);
    createOrMoveMarker(initialCoordinate, !parseCoordinate(initialValueRef.current));

    requestAnimationFrame(() => {
      map.updateSize();
      view.setCenter(initialCoordinate);
      view.setZoom(DEFAULT_ZOOM);
    });

    const updateResolution = () => setScale(scaleFromResolution(view.getResolution()));
    updateResolution();
    view.on("change:resolution", updateResolution);

    map.on("pointermove", (event) => {
      setMouseLocation(formatLonLat(event.coordinate));
    });

    map.on("singleclick", (event) => {
      createOrMoveMarker(event.coordinate, true);
    });

    return () => {
      view.un("change:resolution", updateResolution);
      map.setTarget(undefined);
      mapRef.current = null;
      vectorSourceRef.current = null;
      markerFeatureRef.current = null;
      modifyInteractionRef.current = null;
    };
  }, [createOrMoveMarker]);

  useEffect(() => {
    const currentCoordinate = parseCoordinate(value);
    const markerCoordinate = markerFeatureRef.current?.getGeometry()?.getCoordinates();

    if (!currentCoordinate || !markerCoordinate) {
      return;
    }

    if (
      Math.abs(currentCoordinate[0] - markerCoordinate[0]) > 0.01
      || Math.abs(currentCoordinate[1] - markerCoordinate[1]) > 0.01
    ) {
      createOrMoveMarker(currentCoordinate, false);
    }
  }, [createOrMoveMarker, value]);

  const resetToCochabamba = () => {
    const map = mapRef.current;
    if (!map) {
      return;
    }

    createOrMoveMarker(COCHABAMBA_CENTER, true);
    map.getView().animate({
      center: COCHABAMBA_CENTER,
      zoom: DEFAULT_ZOOM,
      duration: 250,
    });
  };

  return (
    <div className="space-y-3">
      <div className="relative overflow-hidden rounded-xl border border-border/80 bg-muted/30">
        <div ref={mapElementRef} id="map" className="h-[360px] w-full" />

        <div id="wrapper" className="absolute bottom-3 left-3 right-3 flex flex-wrap items-center justify-between gap-2 text-xs">
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
    </div>
  );
}
