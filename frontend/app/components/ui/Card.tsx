import React from "react";
import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  children: React.ReactNode;
  gradient?: boolean;
}

export function Card({ className, children, gradient = false, ...props }: CardProps) {
  return (
    <div
      className={cn(
        "rounded-3xl border border-slate-800 bg-slate-900/50 backdrop-blur-xl transition-all duration-300",
        gradient && "bg-gradient-to-br from-slate-900/80 via-slate-900/50 to-slate-800/20",
        "hover:border-slate-700/50 hover:shadow-2xl hover:shadow-indigo-500/5",
        className
      )}
      {...props}
    >
      {children}
    </div>
  );
}

export function CardHeader({ className, children, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={cn("p-6 flex items-center justify-between border-b border-slate-800/50", className)} {...props}>
      {children}
    </div>
  );
}

export function CardTitle({ className, children, ...props }: React.HTMLAttributes<HTMLHeadingElement>) {
  return (
    <h3 className={cn("text-lg font-semibold text-slate-100 tracking-tight", className)} {...props}>
      {children}
    </h3>
  );
}

export function CardContent({ className, children, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={cn("p-6", className)} {...props}>
      {children}
    </div>
  );
}
