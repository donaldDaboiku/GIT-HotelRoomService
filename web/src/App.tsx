import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { AdminLayout } from './admin/AdminLayout'
import { HotelPage } from './admin/HotelPage'
import { PrintPage } from './admin/PrintPage'
import { RoomsPage } from './admin/RoomsPage'
import { MenuAdminPage, ServicesPage } from './admin/CatalogPages'
import { AuditPage, ReportsPage, StaffAdminPage } from './admin/PeoplePages'
import { GuestHome } from './guest/GuestHome'
import { GuestLayout } from './guest/GuestLayout'
import { MenuPage } from './guest/MenuPage'
import { RequestDetailPage, RequestsPage } from './guest/RequestsPage'
import { ServicesPage as GuestServicesPage } from './guest/ServicesPage'
import { LandingPage } from './pages/LandingPage'
import { PlatformPage } from './platform/PlatformPage'
import { DashboardPage } from './staff/DashboardPage'
import { LoginPage } from './staff/LoginPage'
import { RequestDetailPage as StaffRequestPage } from './staff/RequestDetailPage'
import { RequestListPage } from './staff/RequestListPage'
import { StaffLayout } from './staff/StaffLayout'

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<LandingPage />} />
        <Route path="/staff/login" element={<LoginPage />} />
        <Route path="/platform" element={<PlatformPage />} />
        <Route path="/h/:hotelCode/r/:roomCode" element={<GuestLayout />}>
          <Route index element={<GuestHome />} />
          <Route path="menu" element={<MenuPage />} />
          <Route path="services" element={<GuestServicesPage />} />
          <Route path="requests" element={<RequestsPage />} />
          <Route path="requests/:reference" element={<RequestDetailPage />} />
        </Route>
        <Route element={<StaffLayout />}>
          <Route path="/staff" element={<DashboardPage />} />
          <Route path="/staff/requests" element={<RequestListPage />} />
          <Route path="/staff/requests/:id" element={<StaffRequestPage />} />
          <Route path="/admin" element={<AdminLayout />}>
            <Route index element={<HotelPage />} />
            <Route path="rooms" element={<RoomsPage />} />
            <Route path="rooms/:id/print" element={<PrintPage />} />
            <Route path="services" element={<ServicesPage />} />
            <Route path="menu" element={<MenuAdminPage />} />
            <Route path="staff" element={<StaffAdminPage />} />
            <Route path="reports" element={<ReportsPage />} />
            <Route path="audit" element={<AuditPage />} />
          </Route>
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
