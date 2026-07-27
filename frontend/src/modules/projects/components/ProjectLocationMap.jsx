import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import "leaflet.markercluster";
import "leaflet.markercluster/dist/MarkerCluster.css";
import "leaflet.markercluster/dist/MarkerCluster.Default.css";
import "proj4leaflet";

import { Button } from "@/components/ui/button";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";
import {
  normalizeProjectCoordinates,
  PROJECT_UTM_CRS,
  PROJECT_UTM_DEFINITION,
} from "../lib/project-coordinates";
import { getProjectApprovalLabel } from "../lib/project-status";
import { projectService } from "../services/project.service";

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
const NEARBY_RADIUS_METERS = 500;
const MAP_PROJECT_LIMIT = 500;
const MAP_REFRESH_DELAY_MS = 450;
const CRS_RESOLUTIONS = [1600, 800, 400, 200, 100, 50, 25, 10, 5, 2.5, 1, 0.5, 0.25, 0.125, 0.0625];
const MUNICIPAL_WMS = {
  imagenes: "https://busquedasgamc.cochabamba.bo/web/index.php?r=services/get-imagenes",
  calles: "https://busquedasgamc.cochabamba.bo/web/index.php?r=services/get-nombre-calles",
  catastro: "https://busquedasgamc.cochabamba.bo/web/index.php?r=services/get-info-catastro",
  featureInfo: "https://busquedasgamc.cochabamba.bo/web/index.php?r=services/get-feature-info-url-calles",
};

function parseLatLng(value) {
  const coordinates = normalizeProjectCoordinates(
    value?.latitud,
    value?.longitud,
    value?.coordinate_system,
  );
  return coordinates ? L.latLng(coordinates.lat, coordinates.lng) : null;
}

function formatLatLng(latlng) {
  if (!latlng) {
    return "-";
  }

  return `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`;
}

function formatBounds(bounds) {
  if (!bounds) {
    return "";
  }

  const south = bounds.getSouth();
  const west = bounds.getWest();
  const north = bounds.getNorth();
  const east = bounds.getEast();

  if (![south, west, north, east].every(Number.isFinite)) {
    return "";
  }

  return [south, west, north, east].map((value) => value.toFixed(6)).join(",");
}

function getFeatureInfoUrl(map, crs, layerUrl, latlng, params) {
  const point = map.latLngToContainerPoint(latlng, map.getZoom());
  const size = map.getSize();
  const bounds = map.getBounds();
  const sw = crs.projection._proj.forward([bounds.getSouthWest().lng, bounds.getSouthWest().lat]);
  const ne = crs.projection._proj.forward([bounds.getNorthEast().lng, bounds.getNorthEast().lat]);

  const defaultParams = {
    REQUEST: "GetFeatureInfo",
    SERVICE: "WMS",
    SRS: PROJECT_UTM_CRS,
    STYLES: "",
    VERSION: "1.1.1",
    FORMAT: "image/png",
    BBOX: [sw.join(","), ne.join(",")].join(","),
    HEIGHT: size.y,
    WIDTH: size.x,
    LAYERS: 0,
    QUERY_LAYERS: 0,
  };

  const requestParams = L.Util.extend(defaultParams, params || {});
  requestParams[requestParams.VERSION === "1.3.0" ? "I" : "X"] = Math.round(point.x);
  requestParams[requestParams.VERSION === "1.3.0" ? "J" : "Y"] = Math.round(point.y);

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

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function normalizeProject(project) {
  const coordinates = normalizeProjectCoordinates(
    project.latitude,
    project.longitude,
    project.coordinate_system,
  );
  return coordinates ? { ...project, ...coordinates } : null;
}

function projectPopup(project, distance) {
  const territorial = [
    project.district ? `Distrito ${escapeHtml(project.district)}` : "",
    project.zone ? `Zona ${escapeHtml(project.zone)}` : "",
    project.otb ? `OTB ${escapeHtml(project.otb)}` : "",
  ].filter(Boolean).join(" · ");

  return `
    <div style="min-width:220px;max-width:300px">
      <strong>${escapeHtml(project.name)}</strong>
      <div style="margin-top:6px;color:var(--muted-foreground)">${escapeHtml(project.location || "Sin ubicación registrada")}</div>
      ${territorial ? `<div style="margin-top:4px;color:var(--muted-foreground)">${territorial}</div>` : ""}
      <div style="margin-top:6px"><strong>Condición:</strong> ${escapeHtml(getProjectApprovalLabel(project.approval_status))}</div>
      ${Number.isFinite(distance) ? `<div style="margin-top:4px"><strong>Distancia:</strong> ${Math.round(distance).toLocaleString("es-BO")} m</div>` : ""}
    </div>
  `;
}

function projectIcon(isNearby) {
  const color = isNearby ? "#dc2626" : "#2563eb";

  return L.divIcon({
    className: "",
    html: `<span style="display:block;width:18px;height:18px;border-radius:50%;background:${color};border:3px solid #fff;box-shadow:0 2px 8px rgba(15,23,42,.4)"></span>`,
    iconSize: [18, 18],
    iconAnchor: [9, 9],
    popupAnchor: [0, -12],
  });
}

export default function ProjectLocationMap({ value, onChange, showProjects = false }) {
  const mapElementRef = useRef(null);
  const mapRef = useRef(null);
  const markerRef = useRef(null);
  const crsRef = useRef(null);
  const projectsLayerRef = useRef(null);
  const radiusCircleRef = useRef(null);
  const latestOnChangeRef = useRef(onChange);
  const initialValueRef = useRef(value);
  const [mouseLocation, setMouseLocation] = useState("-");
  const [scale, setScale] = useState("-");
  const [territorialWarning, setTerritorialWarning] = useState("");
  const [projectsMode, setProjectsMode] = useState("hidden");
  const [selectedLocation, setSelectedLocation] = useState(() => parseLatLng(value) || L.latLng(COCHABAMBA_CENTER));
  const [mapBounds, setMapBounds] = useState("");

  const mapProjectParams = useMemo(() => {
    if (!showProjects || projectsMode === "hidden") {
      return null;
    }

    if (projectsMode === "nearby" && selectedLocation) {
      return {
        lat: selectedLocation.lat.toFixed(6),
        lng: selectedLocation.lng.toFixed(6),
        radius: NEARBY_RADIUS_METERS,
        limit: MAP_PROJECT_LIMIT,
      };
    }

    if (projectsMode === "all" && mapBounds) {
      return {
        bbox: mapBounds,
        limit: MAP_PROJECT_LIMIT,
      };
    }

    return null;
  }, [mapBounds, projectsMode, selectedLocation, showProjects]);

  const {
    data: mapProjectsData,
    isFetching: projectsLoading,
    isError: projectsError,
  } = useQuery({
    queryKey: ["projects-map", projectsMode, mapProjectParams],
    queryFn: () => projectService.mapProjects(mapProjectParams ?? {}),
    enabled: Boolean(mapProjectParams),
    staleTime: 5 * 60 * 1000,
    retry: 1,
  });

  const visibleProjects = useMemo(
    () => (mapProjectsData?.data?.items ?? [])
      .map(normalizeProject)
      .filter(Boolean)
      .map((project) => ({
        ...project,
        distance: projectsMode === "nearby"
          ? Number(project.distance ?? selectedLocation?.distanceTo(L.latLng(project.lat, project.lng)))
          : null,
      }))
      .sort((left, right) => {
        if (projectsMode === "nearby") {
          return (left.distance ?? 0) - (right.distance ?? 0);
        }

        return String(left.name ?? "").localeCompare(String(right.name ?? ""), "es");
      }),
    [mapProjectsData, projectsMode, selectedLocation],
  );

  const mapProjectsMeta = mapProjectsData?.data?.meta ?? {};

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
    setSelectedLocation(latlng);
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

    const crs = new L.Proj.CRS(PROJECT_UTM_CRS, PROJECT_UTM_DEFINITION, {
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
    const initialLatLng = parseLatLng(initialValueRef.current);
    const mapCenter = initialLatLng || L.latLng(COCHABAMBA_CENTER);
    setSelectedLocation(mapCenter);
    map.setView(mapCenter, DEFAULT_ZOOM);
    if (initialLatLng) {
      createOrMoveMarker(initialLatLng, false);
    }

    const updateScale = () => {
      const resolution = CRS_RESOLUTIONS[map.getZoom()];
      setScale(Number.isFinite(resolution) ? `1:${Math.round(resolution * 96 * 39.37).toLocaleString("es-BO")}` : "-");
    };
    const updateBounds = () => {
      setMapBounds(formatBounds(map.getBounds()));
    };
    let boundsTimer = null;
    const scheduleBoundsUpdate = () => {
      if (boundsTimer) {
        window.clearTimeout(boundsTimer);
      }
      boundsTimer = window.setTimeout(updateBounds, MAP_REFRESH_DELAY_MS);
    };

    updateScale();
    updateBounds();
    map.on("zoomend", updateScale);
    map.on("zoomend", scheduleBoundsUpdate);
    map.on("moveend", scheduleBoundsUpdate);
    map.on("mousemove", (event) => setMouseLocation(formatLatLng(event.latlng)));
    map.on("click", (event) => createOrMoveMarker(event.latlng, true));

    requestAnimationFrame(() => map.invalidateSize());

    return () => {
      if (boundsTimer) {
        window.clearTimeout(boundsTimer);
      }
      map.remove();
      mapRef.current = null;
      markerRef.current = null;
      crsRef.current = null;
      projectsLayerRef.current = null;
      radiusCircleRef.current = null;
    };
  }, [createOrMoveMarker]);

  useEffect(() => {
    const map = mapRef.current;

    if (!map || !showProjects) {
      return;
    }

    if (projectsLayerRef.current) {
      map.removeLayer(projectsLayerRef.current);
      projectsLayerRef.current = null;
    }

    if (radiusCircleRef.current) {
      map.removeLayer(radiusCircleRef.current);
      radiusCircleRef.current = null;
    }

    if (projectsMode === "hidden" || projectsLoading || projectsError) {
      return;
    }

    const projects = visibleProjects;
    const cluster = L.markerClusterGroup({
      chunkedLoading: true,
      chunkInterval: 100,
      chunkDelay: 30,
      maxClusterRadius: 45,
      showCoverageOnHover: false,
    });

    for (const project of projects) {
      const distance = Number.isFinite(project.distance)
        ? project.distance
        : selectedLocation?.distanceTo(L.latLng(project.lat, project.lng));
      const isNearby = Number.isFinite(distance) && distance <= NEARBY_RADIUS_METERS;
      const marker = L.marker([project.lat, project.lng], {
        icon: projectIcon(isNearby),
        keyboard: true,
        title: project.name,
      });
      marker.bindPopup(projectPopup(project, projectsMode === "nearby" ? distance : null));
      cluster.addLayer(marker);
    }

    cluster.addTo(map);
    projectsLayerRef.current = cluster;

    if (projectsMode === "nearby" && selectedLocation) {
      radiusCircleRef.current = L.circle(selectedLocation, {
        radius: NEARBY_RADIUS_METERS,
        color: "#dc2626",
        weight: 2,
        fillColor: "#ef4444",
        fillOpacity: 0.08,
        interactive: false,
      }).addTo(map);
    }

    return () => {
      if (projectsLayerRef.current === cluster) {
        map.removeLayer(cluster);
        projectsLayerRef.current = null;
      }
    };
  }, [
    projectsError,
    projectsLoading,
    projectsMode,
    selectedLocation,
    showProjects,
    visibleProjects,
  ]);

  useEffect(() => {
    const currentLatLng = parseLatLng(value);
    const map = mapRef.current;
    const markerLatLng = markerRef.current?.getLatLng();

    if (!map) {
      return;
    }

    if (!currentLatLng) {
      if (markerRef.current) {
        map.removeLayer(markerRef.current);
        markerRef.current = null;
      }
      return;
    }

    if (
      !markerLatLng
      || Math.abs(currentLatLng.lat - markerLatLng.lat) > 0.000001
      || Math.abs(currentLatLng.lng - markerLatLng.lng) > 0.000001
    ) {
      createOrMoveMarker(currentLatLng, false);
      map.setView(currentLatLng, map.getZoom());
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

      {showProjects && (
        <div className="space-y-2 rounded-xl border border-border/70 bg-muted/20 p-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="inline-flex overflow-hidden rounded-lg border border-border/80 bg-background">
              {[
                ["hidden", "Ocultar proyectos"],
                ["nearby", "Cercanos (500 m)"],
                ["all", "Proyectos en esta vista"],
              ].map(([mode, label]) => (
                <button
                  key={mode}
                  type="button"
                  onClick={() => setProjectsMode(mode)}
                  className={`min-h-9 border-r border-border/70 px-3 text-sm last:border-r-0 ${
                    projectsMode === mode
                      ? "bg-sky-600 text-white"
                      : "bg-background text-foreground hover:bg-muted"
                  }`}
                >
                  {label}
                </button>
              ))}
            </div>

            {projectsMode !== "hidden" && !projectsLoading && !projectsError && (
              <span className="text-sm text-muted-foreground">
                {projectsMode === "nearby"
                  ? `Mostrando ${visibleProjects.length} proyecto(s) a menos de 500 m`
                  : `Mostrando ${visibleProjects.length} proyecto(s) en esta vista`}
              </span>
            )}
          </div>

          {projectsLoading && (
            <p className="text-sm text-muted-foreground">Cargando proyectos del mapa...</p>
          )}
          {projectsError && (
            <p className="text-sm text-red-600">No se pudieron cargar los proyectos del mapa.</p>
          )}
          {mapProjectsMeta.truncated && !projectsLoading && !projectsError && (
            <p className="text-sm text-amber-700">Hay más proyectos que el límite de {mapProjectsMeta.limit ?? MAP_PROJECT_LIMIT}. Acércate o mueve el mapa para ver más proyectos.</p>
          )}
          {projectsMode === "nearby" && !projectsLoading && !projectsError && visibleProjects.length === 0 && (
            <p className="text-sm text-muted-foreground">No se encontraron proyectos a menos de 500 metros.</p>
          )}
          {projectsMode === "all" && !projectsLoading && !projectsError && visibleProjects.length === 0 && (
            <p className="text-sm text-muted-foreground">No se encontraron proyectos georreferenciados en esta vista.</p>
          )}
        </div>
      )}

      {territorialWarning && (
        <p className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
          {territorialWarning}
        </p>
      )}
    </div>
  );
}
