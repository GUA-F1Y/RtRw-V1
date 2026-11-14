import React from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../providers/useAuth.ts';
import { AuthLoadingBoundary } from './AuthLoadingBoundary.tsx';

export const ProtectedRoute: React.FC = () => {
  const { isAuthenticated, isLoading, isAccountInactive } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return <AuthLoadingBoundary message="Verifying session authorization..." />;
  }

  if (isAccountInactive) {
    return <Navigate to="/inactive" replace />;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  return <Outlet />;
};
