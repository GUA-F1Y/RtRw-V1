import React from 'react';
import { RouterProvider } from 'react-router-dom';
import { AuthProvider } from './app/providers/AuthProvider';
import { QueryProvider } from './app/providers/QueryProvider';
import { router } from './app/router/routes';

export const App: React.FC = () => {
  return (
    <QueryProvider>
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>
    </QueryProvider>
  );
};

export default App;
