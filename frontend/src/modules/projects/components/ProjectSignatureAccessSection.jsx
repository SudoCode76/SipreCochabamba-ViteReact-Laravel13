import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Loader2, Search, Users } from "lucide-react";

import { Input } from "@/components/ui/input";
import { projectService } from "../services/project.service";

function mergePeople(people, authorizedUsers) {
  const byId = new Map();

  people.forEach((person) => byId.set(Number(person.id), person));
  authorizedUsers.forEach((person) => {
    if (!byId.has(Number(person.id))) {
      byId.set(Number(person.id), person);
    }
  });

  return [...byId.values()].sort((a, b) => (a.full_name || "").localeCompare(b.full_name || ""));
}

export default function ProjectSignatureAccessSection({
  projectId,
  people = [],
  draftValue = null,
  onDraftChange = null,
  creatorId = 0,
}) {
  const { data, isLoading, isError } = useQuery({
    queryKey: ["project-signature-access", projectId],
    queryFn: () => projectService.signatureAccess(projectId),
    enabled: Boolean(projectId),
  });
  const configuration = data?.data;

  if (!projectId) {
    const normalizedCreatorId = Number(creatorId);
    const selectedIds = [...new Set([
      normalizedCreatorId,
      ...(draftValue?.user_ids ?? []).map(Number),
    ].filter(Boolean))];
    const creator = people.find((person) => Number(person.id) === normalizedCreatorId) ?? {
      id: normalizedCreatorId,
      full_name: "Usuario creador",
      is_creator: true,
    };
    const authorizedUsers = mergePeople(
      people.filter((person) => selectedIds.includes(Number(person.id))),
      [creator],
    );

    return (
      <ProjectSignatureAccessEditor
        projectId="new"
        people={people}
        onDraftChange={onDraftChange}
        configuration={{
          mode: draftValue?.mode || "selected",
          creator,
          authorized_users: authorizedUsers,
          can_manage: true,
        }}
      />
    );
  }

  if (isLoading) {
    return (
      <section className="flex items-center justify-center rounded-2xl border border-border/70 p-6 text-sm text-muted-foreground">
        <Loader2 className="mr-2 size-4 animate-spin" /> Cargando configuración de firmas...
      </section>
    );
  }

  if (isError || !configuration) {
    return <section className="rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">No se pudo cargar la configuración de firmantes.</section>;
  }

  const editorKey = `${configuration.root_project_id}-${configuration.mode}-${configuration.authorized_users.map((user) => user.id).join("-")}`;

  return (
    <ProjectSignatureAccessEditor
      key={editorKey}
      projectId={projectId}
      people={people}
      configuration={configuration}
      onDraftChange={onDraftChange}
    />
  );
}

function ProjectSignatureAccessEditor({ projectId, people, configuration, onDraftChange = null }) {
  const [mode, setMode] = useState(configuration.mode || "selected");
  const [selectedIds, setSelectedIds] = useState(
    configuration.authorized_users.map((user) => Number(user.id)),
  );
  const [search, setSearch] = useState("");

  const creatorId = Number(configuration.creator?.id || 0);
  const users = useMemo(
    () => mergePeople(people, configuration.authorized_users),
    [configuration.authorized_users, people],
  );
  const filteredUsers = useMemo(() => {
    const term = search.trim().toLowerCase();

    if (!term) {
      return users;
    }

    return users.filter((user) => `${user.full_name || ""} ${user.username || ""}`.toLowerCase().includes(term));
  }, [search, users]);

  const toggleUser = (userId) => {
    if (!configuration?.can_manage || userId === creatorId) {
      return;
    }

    setSelectedIds((current) => {
      const next = current.includes(userId)
        ? current.filter((id) => id !== userId)
        : [...current, userId];
      onDraftChange?.({ mode, user_ids: next });

      return next;
    });
  };

  const changeMode = (nextMode) => {
    setMode(nextMode);
    onDraftChange?.({ mode: nextMode, user_ids: selectedIds });
  };

  return (
    <section className="rounded-2xl border border-border/70 bg-muted/20 p-5">
      <div className="flex items-start gap-3">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-white">
          <Users className="size-5" />
        </span>
        <div>
          <h3 className="font-semibold text-foreground">Quiénes pueden firmar</h3>
          <p className="mt-1 text-sm text-muted-foreground">
            Esta configuración se aplica a todas las versiones. Los permisos globales de firma y del reporte siguen siendo obligatorios.
          </p>
        </div>
      </div>

      <div className="mt-5 grid gap-3 sm:grid-cols-2">
        {[
          ["selected", "Solo personas seleccionadas", "El creador original siempre permanece autorizado."],
          ["all", "Cualquier usuario con permisos", "No reemplaza los permisos globales ni la habilitación del reporte."],
        ].map(([value, label, description]) => (
          <label key={value} className={`flex cursor-pointer gap-3 rounded-2xl border p-4 ${mode === value ? "border-slate-900 bg-white" : "border-border/70 bg-background/70"}`}>
            <input
              type="radio"
              name={`signature-access-${projectId}`}
              value={value}
              checked={mode === value}
              onChange={() => configuration.can_manage && changeMode(value)}
              disabled={!configuration.can_manage}
              className="mt-1"
            />
            <span>
              <span className="block text-sm font-semibold text-foreground">{label}</span>
              <span className="mt-1 block text-xs leading-5 text-muted-foreground">{description}</span>
            </span>
          </label>
        ))}
      </div>

      {mode === "selected" ? (
        <div className="mt-5">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar por nombre o usuario" className="h-11 rounded-xl pl-9" />
          </div>
          <div className="mt-3 max-h-64 space-y-2 overflow-y-auto rounded-2xl border border-border/70 bg-background p-3">
            {filteredUsers.map((user) => {
              const userId = Number(user.id);
              const isCreator = userId === creatorId;

              return (
                <label key={userId} className="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-muted/60">
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(userId)}
                    onChange={() => toggleUser(userId)}
                    disabled={!configuration.can_manage || isCreator}
                  />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium text-foreground">{user.full_name || `Usuario ${userId}`}</span>
                    <span className="block truncate text-xs text-muted-foreground">{user.username || "Sin usuario"}{user.status && user.status !== "AC" ? " · Inactivo" : ""}</span>
                  </span>
                  {isCreator ? <span className="rounded-full bg-sky-100 px-2 py-1 text-[10px] font-semibold text-sky-700">CREADOR</span> : null}
                </label>
              );
            })}
          </div>
        </div>
      ) : (
        <p className="mt-5 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-800">
          La lista de {selectedIds.length} persona(s) queda guardada y se recuperará si vuelve al modo restringido.
        </p>
      )}

    </section>
  );
}
