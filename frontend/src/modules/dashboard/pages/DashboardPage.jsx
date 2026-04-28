import { LayoutDashboard, Users, FileText, Package, BarChart3, Settings, LogOut } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export default function DashboardPage() {
  const stats = [
    { title: "Proyectos Activos", value: "12", icon: FileText, color: "text-blue-600" },
    { title: "Usuarios del Sistema", value: "48", icon: Users, color: "text-amber-600" },
    { title: "Ítems Registrados", value: "1,240", icon: Package, color: "text-green-600" },
    { title: "Reportes Generados", value: "85", icon: BarChart3, color: "text-purple-600" },
  ];

  return (
    <div className="space-y-8 animate-in fade-in duration-700">
      <div>
        <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Panel de Control</h1>
        <p className="text-slate-500 dark:text-slate-400">Bienvenido al Sistema de Gestión de Proyectos e Insumos (SIPRE).</p>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat, i) => (
          <Card key={i} className="border-none shadow-sm hover:shadow-md transition-shadow">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-slate-600">{stat.title}</CardTitle>
              <stat.icon className={`h-4 w-4 ${stat.color}`} />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stat.value}</div>
              <p className="text-xs text-slate-400 mt-1">+2% desde el último mes</p>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
        <Card className="col-span-4 border-none shadow-sm">
          <CardHeader>
            <CardTitle>Proyectos Recientes</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {[1, 2, 3].map((item) => (
                <div key={item} className="flex items-center justify-between p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50">
                  <div className="flex items-center gap-3">
                    <div className="h-10 w-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-700 font-bold">
                      P{item}
                    </div>
                    <div>
                      <p className="text-sm font-semibold">Proyecto de Infraestructura {item}</p>
                      <p className="text-xs text-slate-400">Actualizado hace 2 horas</p>
                    </div>
                  </div>
                  <div className="text-xs font-medium px-2 py-1 rounded bg-green-100 text-green-700 uppercase">
                    Activo
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
        
        <Card className="col-span-3 border-none shadow-sm">
          <CardHeader>
            <CardTitle>Accesos Rápidos</CardTitle>
          </CardHeader>
          <CardContent className="grid grid-cols-2 gap-2">
             <button className="flex flex-col items-center justify-center p-4 rounded-xl bg-slate-50 hover:bg-amber-50 hover:text-amber-700 transition-colors gap-2 border border-slate-100">
                <Package className="h-6 w-6" />
                <span className="text-xs font-bold">Nuevo Ítem</span>
             </button>
             <button className="flex flex-col items-center justify-center p-4 rounded-xl bg-slate-50 hover:bg-amber-50 hover:text-amber-700 transition-colors gap-2 border border-slate-100">
                <FileText className="h-6 w-6" />
                <span className="text-xs font-bold">Nuevo Proy</span>
             </button>
             <button className="flex flex-col items-center justify-center p-4 rounded-xl bg-slate-50 hover:bg-amber-50 hover:text-amber-700 transition-colors gap-2 border border-slate-100">
                <Users className="h-6 w-6" />
                <span className="text-xs font-bold">Usuarios</span>
             </button>
             <button className="flex flex-col items-center justify-center p-4 rounded-xl bg-slate-50 hover:bg-amber-50 hover:text-amber-700 transition-colors gap-2 border border-slate-100">
                <BarChart3 className="h-6 w-6" />
                <span className="text-xs font-bold">Reportes</span>
             </button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
